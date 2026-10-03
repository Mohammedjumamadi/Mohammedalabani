<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Models\DetectedIndicator;
use App\Models\SharedReport;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesAiService;
use Tests\TestCase;

class AccessAndSharingTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
    }

    /** طلب مكتمل بنتيجة وملف حقيقي على القرص الوهمي. */
    private function completed(User $owner, float $manipulation = 82): VerificationRequest
    {
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);
        Storage::disk('private')->put($request->mediaFile->path, $this->jpegBytes());
        $result = VerificationResult::factory()->forManipulation($manipulation)->create(['verification_request_id' => $request->id]);
        DetectedIndicator::factory()->create(['verification_result_id' => $result->id, 'code' => 'ela_anomaly']);

        return $request->fresh();
    }

    public function test_the_owner_sees_the_full_result_page_with_all_required_sections(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);
        $media = $request->mediaFile;

        $this->actingAs($owner)->get(route('verifications.show', $request))
            ->assertOk()
            ->assertSee($request->reference_number)
            ->assertSee($media->original_name)
            ->assertSee($media->sha256)
            ->assertSee('test-model')
            ->assertSee(__('enums.classification.fake'))
            ->assertSee('verdict-fake', false)
            ->assertSee('bi-x-octagon-fill', false)               // الأيقونة لا اللون وحده
            ->assertSee(__('verifications.disclaimer_long'))       // تنبيه حدود الدقة
            ->assertSee(__('verifications.show.download_pdf'))
            ->assertSee(__('indicators.ela_anomaly.title'));
    }

    public function test_each_classification_uses_its_own_colour_class_text_and_icon(): void
    {
        $owner = User::factory()->create();

        foreach ([[10, 'verdict-real', 'bi-check-circle-fill', 'real'], [50, 'verdict-uncertain', 'bi-exclamation-triangle-fill', 'uncertain'], [90, 'verdict-fake', 'bi-x-octagon-fill', 'fake']] as [$p, $css, $icon, $key]) {
            $request = $this->completed($owner, $p);
            $this->actingAs($owner)->get(route('verifications.show', $request))
                ->assertSee($css, false)->assertSee($icon, false)->assertSee(__('enums.classification.'.$key));
        }
    }

    public function test_other_users_cannot_view_download_delete_or_read_the_media_of_someone_elses_request(): void
    {
        $request = $this->completed(User::factory()->create());
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->get(route('verifications.show', $request))->assertForbidden();
        $this->actingAs($intruder)->get(route('verifications.media', $request))->assertForbidden();
        $this->actingAs($intruder)->get(route('reports.pdf', $request))->assertForbidden();
        $this->actingAs($intruder)->delete(route('verifications.destroy', $request))->assertForbidden();
        $this->actingAs($intruder)->post(route('verifications.share.store', $request))->assertForbidden();
        $this->assertNotNull($request->fresh());
    }

    public function test_frames_are_only_served_for_the_matching_request(): void
    {
        $owner = User::factory()->create();
        $mine = $this->completed($owner);
        $other = $this->completed(User::factory()->create());

        Storage::disk('private')->put('frames/x/f.jpg', $this->jpegBytes());
        $indicator = $other->result->indicators()->first();
        $indicator->update(['frame_path' => 'frames/x/f.jpg']);

        $this->actingAs($owner)->get(route('verifications.frame', [$mine, $indicator]))->assertNotFound();
        $this->actingAs($other->user)->get(route('verifications.frame', [$other, $indicator]))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_media_is_served_through_a_protected_route_with_safe_headers(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);

        $response = $this->actingAs($owner)->get(route('verifications.media', $request));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_admin_can_view_but_not_share_a_users_report(): void
    {
        $request = $this->completed(User::factory()->create());
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('verifications.show', $request))->assertOk();
        $this->actingAs($admin)->post(route('verifications.share.store', $request))->assertForbidden();
    }

    public function test_deleting_removes_files_result_indicators_and_shares(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);
        $path = $request->mediaFile->path;
        SharedReport::issue($request, $owner);

        $this->actingAs($owner)->delete(route('verifications.destroy', $request))->assertRedirect(route('verifications.index'));

        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertDatabaseCount('verification_results', 0);
        $this->assertDatabaseCount('detected_indicators', 0);
        $this->assertDatabaseCount('shared_reports', 0);
        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_history_lists_only_own_requests_and_filters_work(): void
    {
        $owner = User::factory()->create();
        $mine = $this->completed($owner, 90);
        $this->completed($owner, 10);
        $theirs = $this->completed(User::factory()->create());

        $this->actingAs($owner)->get(route('verifications.index'))
            ->assertOk()->assertSee($mine->reference_number)->assertDontSee($theirs->reference_number);

        $this->actingAs($owner)->get(route('verifications.index', ['classification' => 'fake']))
            ->assertSee($mine->reference_number)->assertDontSee(VerificationRequest::where('user_id', $owner->id)->orderBy('id')->skip(1)->first()->reference_number);

        $this->actingAs($owner)->get(route('verifications.index', ['q' => $mine->reference_number]))->assertSee($mine->reference_number);
    }

    public function test_share_link_lifecycle(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);

        $this->actingAs($owner)->post(route('verifications.share.store', $request), ['hide_owner' => '1', 'expires_in' => '7'])
            ->assertRedirect();

        $shared = SharedReport::firstOrFail();
        $token = $shared->token();
        $this->assertNotNull($shared->expires_at);
        $this->actingAs($owner)->get(route('verifications.show', $request))->assertSee(route('reports.public', $token));

        // الزائر يرى التقرير بلا بيانات المالك
        auth()->logout();
        $this->get(route('reports.public', $token))
            ->assertOk()
            ->assertSee($request->reference_number)
            ->assertSee(__('verifications.disclaimer_long'))
            ->assertSee('noindex', false)
            ->assertDontSee($owner->name)
            ->assertDontSee($owner->email);
        $this->assertSame(1, $shared->fresh()->views_count);

        // الإلغاء يوقف الرابط فورًا
        $this->actingAs($owner)->delete(route('verifications.share.destroy', $request))->assertRedirect();
        auth()->logout();
        $this->get(route('reports.public', $token))->assertNotFound();
    }

    public function test_creating_a_new_share_link_revokes_the_previous_one(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);

        $this->actingAs($owner)->post(route('verifications.share.store', $request));
        $first = SharedReport::firstOrFail()->token();
        $this->actingAs($owner)->post(route('verifications.share.store', $request));

        $this->assertNull(SharedReport::findActiveByToken($first));
        $this->assertSame(1, SharedReport::whereNull('revoked_at')->count());
    }

    public function test_owner_name_is_shown_only_when_the_owner_chose_so(): void
    {
        $owner = User::factory()->create(['name' => 'Visible Person']);
        $request = $this->completed($owner);
        $shared = SharedReport::issue($request, $owner, hideOwner: false);

        $this->get(route('reports.public', $shared->token()))->assertSee('Visible Person');
    }

    public function test_public_media_is_hidden_unless_preview_sharing_was_enabled(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);
        $hidden = SharedReport::issue($request, $owner, showPreview: false);

        $this->get(route('reports.public.media', $hidden->token()))->assertNotFound();
        $this->get(route('reports.public', $hidden->token()))->assertDontSee(route('reports.public.media', $hidden->token()), false);

        $visible = SharedReport::issue($request, $owner, showPreview: true);
        $this->get(route('reports.public.media', $visible->token()))->assertOk();
    }

    public function test_invalid_expired_and_guessed_tokens_all_return_the_same_404(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);
        $expired = SharedReport::issue($request, $owner, expiresAt: now()->subDay());

        foreach ([str_repeat('a', 48), 'short', $expired->token(), str_repeat('!', 48)] as $token) {
            $this->get('/r/'.$token)->assertNotFound();
        }
    }

    public function test_share_endpoints_reject_unknown_options(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);

        $this->actingAs($owner)->post(route('verifications.share.store', $request), ['expires_in' => '9999'])
            ->assertSessionHasErrors('expires_in');
    }

    public function test_reference_verification_shows_limited_public_details_only(): void
    {
        $owner = User::factory()->create(['name' => 'Private Owner']);
        $request = $this->completed($owner);

        $this->get(route('reports.verify', $request->reference_number))
            ->assertOk()->assertSee($request->mediaFile->sha256)->assertSee(__('reports.verify.found'))
            ->assertDontSee('Private Owner')->assertDontSee($request->mediaFile->original_name);

        $this->get(route('reports.verify', 'AMV-2026-NOSUCHREF'))->assertOk()->assertSee(__('reports.verify.not_found'));
        $this->post(route('reports.verify.check'), ['reference' => strtolower($request->reference_number)])
            ->assertRedirect(route('reports.verify', $request->reference_number));
        $this->post(route('reports.verify.check'), ['reference' => '<script>'])->assertSessionHasErrors('reference');
    }

    public function test_the_pdf_report_is_generated_with_a_qr_code_and_arabic_text(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);

        $response = $this->actingAs($owner)->get(route('reports.pdf', $request));

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(8_000, strlen($pdf));
        $this->assertDatabaseHas('activity_logs', ['action' => 'report.downloaded']);
    }

    public function test_the_pdf_is_unavailable_for_requests_without_a_result(): void
    {
        $owner = User::factory()->create();
        $request = VerificationRequest::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('reports.pdf', $request))->assertNotFound();
    }

    public function test_expired_files_are_purged_but_results_remain(): void
    {
        $owner = User::factory()->create();
        $request = $this->completed($owner);
        $path = $request->mediaFile->path;
        $request->mediaFile->update(['expires_at' => now()->subMinute()]);

        $this->artisan('media:purge-expired')->assertSuccessful();

        Storage::disk('private')->assertMissing($path);
        $this->assertNotNull($request->fresh()->result);
        $this->assertNotNull($request->fresh()->mediaFile->file_deleted_at);
        $this->actingAs($owner)->get(route('verifications.show', $request))->assertOk()->assertSee(__('verifications.show.file_unavailable'));
    }

    public function test_users_can_delete_all_their_data_after_confirming_the_password(): void
    {
        $owner = User::factory()->create(['password' => 'Secret123']);
        $request = $this->completed($owner);
        $other = $this->completed(User::factory()->create());

        $this->actingAs($owner)->from('/settings')->delete(route('settings.data.delete'), ['delete_password' => 'wrong'])
            ->assertSessionHasErrors('delete_password');
        $this->assertNotNull($request->fresh());

        $this->actingAs($owner)->delete(route('settings.data.delete'), ['delete_password' => 'Secret123'])->assertSessionHas('success');

        $this->assertNull(VerificationRequest::find($request->id));
        $this->assertNotNull(VerificationRequest::find($other->id), 'بيانات الآخرين لا تُمس');
    }

    public function test_notifications_belong_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $note = $owner->userNotifications()->create(['type' => 'x', 'title' => 'T', 'body' => 'B', 'url' => url('/dashboard')]);

        $this->actingAs(User::factory()->create())->get(route('notifications.open', $note->id))->assertNotFound();
        $this->actingAs($owner)->get(route('notifications.open', $note->id))->assertRedirect(url('/dashboard'));
        $this->assertTrue($note->fresh()->isRead());
    }

    public function test_dashboard_reports_real_counts(): void
    {
        $owner = User::factory()->create();
        $this->completed($owner, 90);
        $this->completed($owner, 10);
        $this->completed($owner, 50);
        VerificationRequest::factory()->failed()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertViewHas('stats', fn ($s) => $s === ['total' => 4, 'real' => 1, 'fake' => 1, 'uncertain' => 1, 'failed' => 1]);
    }
}
