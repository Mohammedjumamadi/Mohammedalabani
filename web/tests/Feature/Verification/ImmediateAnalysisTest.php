<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Services\Verification\AnalysisDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakesAiService;
use Tests\TestCase;

/**
 * الصور تُحلَّل فور الرفع دون انتظار عامل الطابور، حتى لا تعلق على «في قائمة الانتظار 5%»
 * إن كان العامل متوقفًا أو لا يستمع إلى طابور analysis. نستخدم طابور قاعدة البيانات كما في التشغيل الفعلي.
 */
class ImmediateAnalysisTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
        config(['queue.default' => 'database']);
    }

    private function uploadImage(User $user)
    {
        return $this->actingAs($user)->post(route('verifications.store'), [
            'media_type' => 'image',
            'file' => UploadedFile::fake()->createWithContent('a.jpg', $this->jpegBytes()),
            'consent' => '1',
        ]);
    }

    public function test_an_image_is_analyzed_right_after_upload_without_a_queue_worker(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));

        $this->uploadImage(User::factory()->create())->assertRedirect();

        $request = VerificationRequest::firstOrFail();
        $this->assertSame(RequestStatus::Completed, $request->status);
        $this->assertNotNull($request->result);
        $this->assertSame(0, DB::table('jobs')->count(), 'لا شيء يجب أن ينتظر عامل الطابور');
    }

    public function test_when_the_service_is_down_the_image_falls_back_to_the_queue_instead_of_hanging(): void
    {
        $this->fakeAiDown();

        $this->uploadImage(User::factory()->create())->assertRedirect();

        $request = VerificationRequest::firstOrFail();
        $this->assertSame(RequestStatus::Pending, $request->status);
        $this->assertSame('queued', $request->stage);

        $job = DB::table('jobs')->sole();
        $this->assertSame('analysis', $job->queue);
        $this->assertGreaterThan(now()->timestamp, $job->available_at, 'إعادة المحاولة بعد ثوانٍ لا فورًا');

        // عند عودة الخدمة يكمل عامل الطابور الطلب نفسه
        $this->fakeAiReturns($this->fakeAnalysis(20));
        $this->travel(10)->seconds();
        $this->artisan('queue:work', ['--queue' => 'analysis,default', '--once' => true, '--stop-when-empty' => true]);

        $this->assertSame(RequestStatus::Completed, $request->refresh()->status);
    }

    public function test_video_and_audio_still_go_through_the_analysis_queue(): void
    {
        $user = User::factory()->create();
        $media = MediaFile::factory()->video()->create(['user_id' => $user->id]);
        $request = VerificationRequest::factory()->create(['user_id' => $user->id, 'media_file_id' => $media->id]);

        app(AnalysisDispatcher::class)->dispatch($request->id);

        $this->assertSame('analysis', DB::table('jobs')->sole()->queue);
    }

    public function test_immediate_image_analysis_can_be_switched_off(): void
    {
        config(['aimv.ai.immediate_images' => false]);
        $this->fakeAiReturns($this->fakeAnalysis(82));

        $this->uploadImage(User::factory()->create())->assertRedirect();

        $this->assertSame(RequestStatus::Pending, VerificationRequest::firstOrFail()->status);
        $this->assertSame('analysis', DB::table('jobs')->sole()->queue);
    }
}
