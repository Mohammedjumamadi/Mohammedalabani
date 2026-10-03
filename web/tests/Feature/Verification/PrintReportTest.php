<?php

namespace Tests\Feature\Verification;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\FakesAiService;
use Tests\TestCase;

/** «طباعة التقرير»: مسار الزائر إلى الدخول/التسجيل ثم العودة للنتيجة نفسها، وحماية مسار الطباعة على الخادم. */
class PrintReportTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
    }

    private function guestUpload(float $manipulation = 82): VerificationRequest
    {
        $this->fakeAiReturns($this->fakeAnalysis($manipulation));
        $this->post(route('guest.verify'), [
            'media_type' => 'image', 'file' => UploadedFile::fake()->createWithContent('photo.jpg', $this->jpegBytes()), 'consent' => '1',
        ]);

        return VerificationRequest::firstOrFail();
    }

    public function test_the_guest_result_shows_the_print_button_and_the_account_prompt(): void
    {
        $verification = $this->guestUpload();

        $this->get(route('guest.show', $verification))
            ->assertOk()
            ->assertSee(__('reports.print.button'))
            ->assertSee(__('home_upload.print_modal_title'))
            ->assertSee(route('guest.report.access', [$verification, 'register']))
            ->assertSee(route('guest.report.access', [$verification, 'login']))
            ->assertDontSee(route('verifications.print', $verification));
    }

    public function test_a_guest_cannot_open_the_print_route_directly(): void
    {
        $verification = $this->guestUpload();

        $this->get(route('verifications.print', $verification))->assertRedirect(route('login'));
    }

    public function test_guest_signs_in_and_returns_to_print_the_same_result(): void
    {
        $verification = $this->guestUpload();
        $user = User::factory()->create(['password' => 'Secret123!']);

        $this->get(route('guest.report.access', [$verification, 'login']))->assertRedirect(route('login'));
        $this->get(route('login'))->assertSee(__('landing.auth.resume_title'));

        $this->post(route('login'), ['email' => $user->email, 'password' => 'Secret123!'])
            ->assertRedirect(route('guest.claim', $verification));

        $this->get(route('guest.claim', $verification))
            ->assertRedirect(route('verifications.print', [$verification, 'autoprint' => 1]));

        $verification->refresh();
        $this->assertSame($user->id, $verification->user_id);
        $this->assertSame($user->id, $verification->mediaFile->user_id);
        // صار ملفًا لحساب حقيقي: مدة الاحتفاظ العادية بالأيام بدل ساعات الزائر
        $this->assertTrue($verification->mediaFile->expires_at->greaterThan(now()->addDays(2)));

        $this->get(route('verifications.print', $verification))
            ->assertOk()
            ->assertSee($verification->reference_number)
            ->assertSee('photo.jpg')
            ->assertSee(__('enums.classification.fake'))
            ->assertSee(number_format($verification->result->confidence_score, 1).'%')
            ->assertSee($verification->result->indicators->first()->title());
    }

    public function test_guest_registers_and_returns_to_print_the_same_result(): void
    {
        $verification = $this->guestUpload(10);

        $this->get(route('guest.report.access', [$verification, 'register']))->assertRedirect(route('register'));

        $this->post(route('register'), [
            'name' => 'Sara', 'email' => 'sara@example.com', 'phone' => '0912345678', 'city' => 'Benghazi',
            'password' => 'Secret123!', 'password_confirmation' => 'Secret123!', 'terms' => '1',
        ])->assertRedirect(route('guest.claim', $verification));

        $this->followingRedirects()->get(route('guest.claim', $verification))
            ->assertOk()
            ->assertSee(__('reports.print.title'))
            ->assertSee(__('reports.print.claimed'))
            ->assertSee(__('enums.classification.real'));

        $this->assertSame(User::where('email', 'sara@example.com')->value('id'), $verification->fresh()->user_id);
    }

    public function test_another_user_cannot_claim_a_guest_result_from_a_different_browser(): void
    {
        $verification = $this->guestUpload();

        $this->flushSession();
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('guest.claim', $verification))
            ->assertRedirect(route('dashboard'))->assertSessionHas('error');
        $this->assertTrue($verification->fresh()->user->isGuestSystem());
    }

    public function test_a_user_cannot_print_another_users_report(): void
    {
        $owner = User::factory()->create();
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);

        $this->actingAs(User::factory()->create())->get(route('verifications.print', $request))->assertForbidden();
        // ولا يستطيع استلامه عبر مسار الزائر حتى لو وضع رقمه المرجعي في جلسته
        session(['guest_verification_refs' => [$request->reference_number]]);
        $this->get(route('guest.claim', $request))->assertRedirect(route('dashboard'));
        $this->assertSame($owner->id, $request->fresh()->user_id);
    }

    public function test_the_owner_can_print_and_an_unfinished_request_has_no_report(): void
    {
        $owner = User::factory()->create();
        $done = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);
        VerificationResult::factory()->forManipulation(82)->create(['verification_request_id' => $done->id]);
        $pending = VerificationRequest::factory()->status(RequestStatus::Analyzing)->create(['user_id' => $owner->id]);

        $this->actingAs($owner)->get(route('verifications.print', $done))->assertOk()->assertSee($done->reference_number);
        $this->actingAs($owner)->get(route('verifications.print', $pending))->assertNotFound();
        $this->actingAs($owner)->get(route('verifications.show', $done))->assertSee(route('verifications.print', [$done, 'autoprint' => 1]), false);
    }
}
