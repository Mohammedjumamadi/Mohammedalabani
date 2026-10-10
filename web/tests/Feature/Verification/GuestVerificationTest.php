<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesAiService;
use Tests\TestCase;

class GuestVerificationTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
    }

    private function upload(array $extra = [])
    {
        return $this->post(route('guest.verify'), array_merge([
            'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent('a.jpg', $this->jpegBytes()), 'consent' => '1',
        ], $extra));
    }

    public function test_the_home_page_offers_the_guest_uploader_to_visitors_only(): void
    {
        $this->get('/')->assertOk()->assertSee('uploader(', false)->assertSee(route('guest.verify'));

        $this->actingAs(User::factory()->create())->get('/')
            ->assertOk()->assertDontSee(route('guest.verify'));
    }

    public function test_a_guest_can_upload_and_immediately_view_their_own_result(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));

        $response = $this->withSession([])->upload();
        $verification = VerificationRequest::firstOrFail();

        // الصورة تُحلَّل فور إرسال الاستجابة، فيصل المتصفح إلى صفحة التقدم والنتيجة جاهزة خلال ثوانٍ
        $response->assertRedirect(route('guest.progress', $verification));
        $this->assertSame(RequestStatus::Completed, $verification->status);
        $this->assertTrue($verification->user->isGuestSystem());

        $this->get(route('guest.show', $verification))
            ->assertOk()
            ->assertSee($verification->reference_number)
            ->assertSee(__('enums.classification.fake'))
            ->assertSee(__('home_upload.guest_result_notice'))
            ->assertSee(__('verifications.disclaimer_long'));
    }

    public function test_the_guest_result_page_hides_real_download_and_shows_the_account_prompt_instead(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $this->upload();
        $verification = VerificationRequest::firstOrFail();

        $page = $this->get(route('guest.show', $verification));

        $page->assertOk()
            ->assertSee(__('home_upload.modal_text'))
            ->assertDontSee(route('reports.pdf', $verification));
    }

    public function test_a_guest_cannot_download_the_real_pdf_route_without_logging_in(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $this->upload();
        $verification = VerificationRequest::firstOrFail();

        // مسار PDF الحقيقي يبقى خلف تسجيل الدخول، حتى لو عرف الزائر الرقم المرجعي
        $this->get(route('reports.pdf', $verification))->assertRedirect(route('login'));
    }

    public function test_a_different_browser_session_cannot_view_someone_elses_guest_result(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $this->upload();
        $verification = VerificationRequest::firstOrFail();

        // جلسة جديدة بلا الرقم المرجعي في guest_verification_refs
        $this->flushSession();
        $this->get(route('guest.show', $verification))->assertNotFound();
        $this->get(route('guest.media', $verification))->assertNotFound();
        $this->getJson(route('guest.status', $verification))->assertNotFound();
    }

    public function test_a_logged_in_users_own_verification_cannot_be_opened_through_the_guest_route(): void
    {
        $owner = User::factory()->create();
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);

        // حتى لو خُمِّن الرقم المرجعي الحقيقي ووُضع يدويًا في جلسة الزائر
        session(['guest_verification_refs' => [$request->reference_number]]);
        $this->get(route('guest.show', $request))->assertNotFound();
    }

    public function test_consent_is_required_and_no_ai_call_is_made_without_it(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis());

        $this->upload(['consent' => null])->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_malicious_files_are_rejected_the_same_way_as_the_authenticated_flow(): void
    {
        $malicious = UploadedFile::fake()->createWithContent('shell.php.jpg', $this->jpegBytes());

        $this->upload(['file' => $malicious])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_guest_uploads_expire_much_sooner_than_the_default_retention(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));

        $this->upload();

        $media = VerificationRequest::firstOrFail()->mediaFile;
        $this->assertTrue($media->expires_at->lessThan(now()->addDay()));
        $this->assertTrue($media->expires_at->greaterThan(now()));
    }

    public function test_logged_in_users_are_redirected_away_from_guest_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('guest.verify'), [
            'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent('a.jpg', $this->jpegBytes()), 'consent' => '1',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_guest_uploads_are_rate_limited_per_ip(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('guest.verify'), [
                'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent("a{$i}.jpg", $this->jpegBytes($i)), 'consent' => '1',
            ])->assertStatus(302);
        }

        $this->post(route('guest.verify'), [
            'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent('one-more.jpg', $this->jpegBytes(99)), 'consent' => '1',
        ])->assertStatus(429);
    }

    public function test_a_failed_guest_analysis_shows_a_safe_message_and_no_fake_result(): void
    {
        $this->fakeAiDown();

        $this->upload();
        $verification = VerificationRequest::firstOrFail();

        $this->assertSame(RequestStatus::Failed, $verification->status);
        $this->get(route('guest.show', $verification))
            ->assertOk()
            ->assertSee(__('ai_errors.service_unavailable'))
            ->assertSee(__('home_upload.retry_needs_account'));
    }

    public function test_guest_activity_and_notifications_are_attributed_to_the_system_account_without_emailing_it(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));

        $this->upload();

        $guest = User::guestSystem();
        $this->assertDatabaseHas('activity_logs', ['action' => 'upload.created', 'user_id' => $guest->id]);
        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_guest_system_account_is_hidden_from_admin_user_management_and_counts(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $this->upload();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertDontSee(User::GUEST_EMAIL);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertViewHas('users', fn ($u) => $u['total'] === 1); // المدير فقط
    }

    public function test_the_progress_page_redirects_to_the_result_once_finished(): void
    {
        $owner_ref = null;
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $this->upload();
        $verification = VerificationRequest::firstOrFail();

        $this->get(route('guest.progress', $verification))->assertRedirect(route('guest.show', $verification));
    }

    public function test_the_feature_can_be_disabled_via_config(): void
    {
        config(['aimv.guest.enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee(route('guest.verify'));
        $this->upload()->assertNotFound();
    }
}
