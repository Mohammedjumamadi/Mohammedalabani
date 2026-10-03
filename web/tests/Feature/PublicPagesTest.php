<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\SystemSetting;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function pages(): array
    {
        return ['/', '/about', '/how-it-works', '/supported-content', '/awareness', '/faq', '/privacy', '/terms', '/contact', '/verify-report', '/login', '/register', '/forgot-password'];
    }

    public function test_every_public_page_renders_in_arabic_rtl_and_english_ltr(): void
    {
        $this->seed(ContentSeeder::class);

        foreach ($this->pages() as $url) {
            $this->get($url)->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
        }

        $this->get('/lang/en');
        foreach ($this->pages() as $url) {
            $this->get($url)->assertOk()->assertSee('dir="ltr"', false)->assertSee('lang="en"', false);
        }
    }

    public function test_pages_have_a_single_h1_a_title_and_a_skip_link(): void
    {
        foreach (['/', '/about', '/faq', '/login'] as $url) {
            $html = $this->get($url)->assertOk()->assertSee('skip-link', false)->assertSee('<title>', false)->getContent();
            $this->assertSame(1, substr_count($html, '<h1'), "$url يجب أن يحتوي h1 واحدًا");
        }
    }

    public function test_home_links_to_registration_for_visitors(): void
    {
        $this->get('/')->assertSee(route('register'))->assertSee(__('landing.hero.title'));
    }

    public function test_faq_and_awareness_show_the_seeded_content_in_the_current_language(): void
    {
        $this->seed(ContentSeeder::class);

        $this->get('/faq')->assertSee('هل نتيجة المنصة قاطعة؟');
        $this->get('/awareness')->assertSee('ما هو التزييف العميق');
        $this->get('/lang/en');
        $this->get('/faq')->assertSee('Is the platform result conclusive?');
    }

    public function test_how_it_works_reflects_the_admin_thresholds(): void
    {
        SystemSetting::put('threshold_fake', 85);
        SystemSetting::put('threshold_real', 15);

        $this->get('/how-it-works')->assertSee('85')->assertSee('15');
    }

    public function test_supported_page_lists_only_the_allowed_extensions(): void
    {
        SystemSetting::put('allowed_extensions', ['image' => ['png'], 'video' => ['mp4'], 'audio' => ['mp3']], 'json');

        $this->get('/supported-content')->assertSee('PNG')->assertDontSee('WEBP');
    }

    public function test_the_privacy_and_terms_pages_state_the_key_commitments(): void
    {
        $this->get('/privacy')->assertSee('موافقتك')->assertSee('لتدريب النماذج');
        $this->get('/terms')->assertSee('دليل')->assertSee('خبير');
    }

    public function test_contact_form_sends_a_message_and_validates(): void
    {
        Mail::fake();

        $this->post('/contact', ['name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 'اقتراح', 'message' => 'رسالة طويلة بما يكفي للإرسال'])->assertSessionHas('success');
        Mail::assertSent(ContactMessage::class, fn ($m) => $m->senderEmail === 'ali@example.com');

        $this->post('/contact', ['name' => '', 'email' => 'bad', 'subject' => '', 'message' => 'x'])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
    }

    public function test_contact_form_is_rate_limited(): void
    {
        Mail::fake();
        $payload = ['name' => 'Ali', 'email' => 'ali@example.com', 'subject' => 's', 'message' => 'رسالة طويلة بما يكفي للإرسال'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $payload)->assertRedirect();
        }
        $this->post('/contact', $payload)->assertStatus(429);
    }

    public function test_public_report_verification_page_handles_unknown_references(): void
    {
        $this->get('/verify-report/AMV-2026-NOPE')->assertOk()->assertSee(__('reports.verify.not_found'));
    }

    public function test_public_report_pages_are_rate_limited_against_token_guessing(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/r/'.str_repeat('a', 48))->assertNotFound();
        }
        $this->get('/r/'.str_repeat('b', 48))->assertStatus(429);
    }

    public function test_security_relevant_pages_are_not_indexable_when_shared(): void
    {
        // التقارير المشتركة تحمل noindex (اختُبر في AccessAndSharingTest)، والصفحات العامة العادية لا تحمله
        $this->get('/')->assertDontSee('noindex', false);
    }
}
