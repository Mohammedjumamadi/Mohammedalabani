<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Jobs\AnalyzeMediaJob;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesAiService;
use Tests\TestCase;

/**
 * صفحة التقدم لا تبقى صامتة على «في قائمة الانتظار 5%»: تشرح السبب، وتعيد تشغيل تحليل الصورة بنفسها
 * إن كانت الخدمة تعمل ولا يوجد عامل طابور.
 */
class PendingWatchdogTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
        config(['queue.default' => 'database']);
    }

    /** طلب صورة عالق في الانتظار منذ $age ثانية، وملفه موجود فعلًا على القرص. */
    private function stuckRequest(User $user, int $age = 30, bool $video = false): VerificationRequest
    {
        $factory = MediaFile::factory();
        $media = ($video ? $factory->video() : $factory)->create(['user_id' => $user->id]);
        Storage::disk($media->disk)->put($media->path, $this->jpegBytes());

        $request = VerificationRequest::factory()->create([
            'user_id' => $user->id, 'media_file_id' => $media->id,
            'status' => RequestStatus::Pending, 'progress' => 5, 'stage' => 'queued',
        ]);
        $request->forceFill(['updated_at' => now()->subSeconds($age)])->saveQuietly();

        return $request;
    }

    public function test_a_stuck_image_is_analyzed_by_the_progress_page_when_no_worker_runs(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();
        $request = $this->stuckRequest($user);

        $this->actingAs($user)->getJson(route('verifications.status', $request))
            ->assertOk()->assertJsonPath('hint', 'retrying');

        $this->assertSame(RequestStatus::Completed, $request->refresh()->status);
        $this->actingAs($user)->getJson(route('verifications.status', $request))
            ->assertJsonPath('final', true)->assertJsonPath('hint', null);
    }

    public function test_the_page_does_not_retry_more_than_once_per_interval(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*/health' => Http::response(['status' => 'ok'], 200), '*/v1/analyze' => Http::response([], 502)]);
        $user = User::factory()->create();
        $request = $this->stuckRequest($user);

        $this->actingAs($user)->getJson(route('verifications.status', $request));
        $this->travel(9)->seconds();
        $this->actingAs($user)->getJson(route('verifications.status', $request));

        Http::assertSentCount(2); // فحص الصحة مرة (مخزّن) + محاولة تحليل واحدة فقط
        $this->assertSame(RequestStatus::Pending, $request->refresh()->status);
        $this->assertSame('analysis', DB::table('jobs')->sole()->queue, 'المحاولة الفاشلة تُسلَّم للطابور');
    }

    public function test_the_user_is_told_when_the_analysis_service_is_down(): void
    {
        $this->fakeAiDown();
        $user = User::factory()->create();
        $request = $this->stuckRequest($user);

        $this->actingAs($user)->getJson(route('verifications.status', $request))
            ->assertOk()
            ->assertJsonPath('hint', 'service_offline')
            ->assertJsonPath('hint_text', __('verifications.progress.hints.service_offline'));

        $this->assertSame(RequestStatus::Pending, $request->refresh()->status);
    }

    public function test_admins_are_also_told_how_to_fix_it(): void
    {
        $this->fakeAiDown();
        $admin = User::factory()->admin()->create();
        $request = $this->stuckRequest($admin);

        $text = $this->actingAs($admin)->getJson(route('verifications.status', $request))->json('hint_text');

        $this->assertStringContainsString('fix-aimv-queue.ps1', $text);
    }

    public function test_a_fresh_request_gets_no_hint_and_no_extra_attempt(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();
        $request = $this->stuckRequest($user, age: 2);

        $this->actingAs($user)->getJson(route('verifications.status', $request))->assertJsonPath('hint', null);

        $this->assertSame(RequestStatus::Pending, $request->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_video_waits_for_the_worker_instead_of_running_in_the_page(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82, 'video'));
        $user = User::factory()->create();
        $request = $this->stuckRequest($user, video: true);

        $this->actingAs($user)->getJson(route('verifications.status', $request))->assertJsonPath('hint', 'waiting_worker');

        $this->assertSame(RequestStatus::Pending, $request->refresh()->status);
    }

    public function test_a_request_already_being_analyzed_is_not_analyzed_twice(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();
        $request = $this->stuckRequest($user);

        $lock = Cache::lock("aimv:analyze:{$request->id}", 60);
        $this->assertTrue($lock->get());
        dispatch_sync(new AnalyzeMediaJob($request->id, immediate: true));
        $lock->release();

        $this->assertSame(RequestStatus::Pending, $request->refresh()->status);
        Http::assertNothingSent();
    }

    public function test_guests_see_the_reason_too(): void
    {
        $this->fakeAiDown();
        $this->post(route('guest.verify'), [
            'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent('a.jpg', $this->jpegBytes()), 'consent' => '1',
        ])->assertRedirect();
        $request = VerificationRequest::firstOrFail();
        $this->assertSame('queued', $request->stage);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->travel(10)->seconds();
        $this->getJson(route('guest.status', $request))->assertOk()->assertJsonPath('hint', 'service_offline');
    }
}
