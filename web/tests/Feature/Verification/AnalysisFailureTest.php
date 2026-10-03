<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesAiService;
use Tests\TestCase;

class AnalysisFailureTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
    }

    private function upload(User $user)
    {
        return $this->actingAs($user)->post(route('verifications.store'), [
            'media_type' => 'image',
            'file' => UploadedFile::fake()->createWithContent('a.jpg', $this->jpegBytes()),
            'consent' => '1',
        ]);
    }

    public function test_an_unreachable_service_marks_the_request_failed_with_no_fake_result(): void
    {
        $this->fakeAiDown();
        $user = User::factory()->create();

        $this->upload($user)->assertRedirect();

        $request = VerificationRequest::firstOrFail();
        $this->assertSame(RequestStatus::Failed, $request->status);
        $this->assertSame('service_unavailable', $request->error_message);
        $this->assertNull($request->result, 'لا يجوز إنشاء أي نتيجة عند فشل الخدمة');
        $this->assertDatabaseCount('verification_results', 0);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'verification.failed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'verification.failed']);
    }

    public function test_a_server_error_from_the_service_fails_the_request(): void
    {
        Http::fake(['*/v1/analyze' => Http::response(['detail' => 'boom: C:\\secret\\path'], 500)]);

        $this->upload(User::factory()->create());

        $this->assertSame(RequestStatus::Failed, VerificationRequest::firstOrFail()->status);
    }

    public function test_technical_details_are_never_shown_to_the_user(): void
    {
        Http::fake(['*/v1/analyze' => Http::response(['detail' => 'Traceback at C:\\secret\\path\\model.py'], 500)]);
        $user = User::factory()->create();
        $this->upload($user);
        $request = VerificationRequest::firstOrFail();

        $page = $this->actingAs($user)->get(route('verifications.show', $request));
        $page->assertOk()->assertSee(__('ai_errors.service_unavailable'))
            ->assertDontSee('secret')->assertDontSee('Traceback')->assertDontSee('model.py');

        $this->actingAs($user)->getJson(route('verifications.status', $request))
            ->assertOk()->assertJsonPath('failed', true)->assertJsonMissing(['secret']);
    }

    public function test_a_malformed_response_is_rejected_not_trusted(): void
    {
        foreach ([
            ['media_type' => 'image', 'manipulation_probability' => 250, 'model' => ['name' => 'x', 'version' => '1']],
            ['media_type' => 'image', 'manipulation_probability' => 'high', 'model' => ['name' => 'x', 'version' => '1']],
            ['media_type' => 'image', 'manipulation_probability' => 50],
            ['unexpected' => true],
        ] as $payload) {
            Http::fake(['*/v1/analyze' => Http::response($payload, 200)]);
            $this->upload(User::factory()->create());
        }

        $this->assertSame(4, VerificationRequest::where('status', 'failed')->count());
        $this->assertDatabaseCount('verification_results', 0);
    }

    public function test_the_service_reporting_no_model_fails_with_a_clear_reason(): void
    {
        Http::fake(['*/v1/analyze' => Http::response(['detail' => 'no model'], 503)]);

        $this->upload(User::factory()->create());

        $this->assertSame('no_model', VerificationRequest::firstOrFail()->error_message);
    }

    public function test_a_failed_request_can_be_retried_by_its_owner_only(): void
    {
        $this->fakeAiDown();
        $owner = User::factory()->create();
        $this->upload($owner);
        $request = VerificationRequest::firstOrFail();

        $this->actingAs(User::factory()->create())->post(route('verifications.retry', $request))->assertForbidden();

        $this->fakeAiReturns($this->fakeAnalysis(12));
        $this->actingAs($owner)->post(route('verifications.retry', $request))->assertRedirect();

        $request->refresh();
        $this->assertSame(RequestStatus::Completed, $request->status);
        $this->assertNotNull($request->result);
    }

    public function test_the_status_endpoint_reports_progress_for_the_owner_only(): void
    {
        $owner = User::factory()->create();
        $request = VerificationRequest::factory()->create(['user_id' => $owner->id, 'status' => RequestStatus::Analyzing, 'progress' => 45, 'stage' => 'analyzing']);

        $this->actingAs($owner)->getJson(route('verifications.status', $request))
            ->assertOk()->assertJson(['status' => 'analyzing', 'progress' => 45, 'final' => false, 'url' => null]);

        $this->actingAs(User::factory()->create())->getJson(route('verifications.status', $request))->assertForbidden();
    }

    public function test_progress_page_renders_while_processing_and_redirects_when_done(): void
    {
        $owner = User::factory()->create();
        $pending = VerificationRequest::factory()->create(['user_id' => $owner->id, 'status' => RequestStatus::Analyzing, 'progress' => 45, 'stage' => 'analyzing']);
        $done = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('verifications.progress', $pending))
            ->assertOk()->assertSee('progressTracker(', false)->assertSee($pending->reference_number);
        $this->actingAs($owner)->get(route('verifications.progress', $done))->assertRedirect(route('verifications.show', $done));
    }
}
