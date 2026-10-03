<?php

namespace Tests\Feature\Admin;

use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\ModelVersion;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesAiService;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
        $this->admin = User::factory()->admin()->create(['email' => 'root@example.com']);
        Http::fake(['*' => Http::response(['status' => 'ok', 'version' => '1.0.0', 'device' => 'cpu', 'ffmpeg' => true, 'models' => [['key' => 'image_face', 'loaded' => true, 'error' => null]]], 200)]);
    }

    /** @return list<string> */
    private function adminUrls(): array
    {
        return [route('admin.dashboard'), route('admin.users.index'), route('admin.verifications.index'), route('admin.statistics'),
            route('admin.models.index'), route('admin.content.index'), route('admin.settings.edit'), route('admin.activity.index'), route('admin.statistics.export')];
    }

    public function test_every_admin_page_is_forbidden_to_regular_users_and_guests(): void
    {
        $user = User::factory()->create();

        foreach ($this->adminUrls() as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
            auth()->logout();
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_admin_write_actions_are_forbidden_to_regular_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->put(route('admin.users.status', $other), ['status' => 'suspended'])->assertForbidden();
        $this->actingAs($user)->put(route('admin.settings.update'), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $other))->assertForbidden();
        $this->assertTrue($other->fresh()->isActive());
    }

    public function test_every_admin_page_renders_for_admins_with_demo_data(): void
    {
        $this->seed([RoleSeeder::class, SystemSettingSeeder::class, ContentSeeder::class, UserSeeder::class, DemoDataSeeder::class]);
        ModelVersion::factory()->create(['name' => 'aimv-image-ensemble']);

        foreach ($this->adminUrls() as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->admin)->get(route('admin.users.show', User::where('email', UserSeeder::USER_EMAIL)->first()))->assertOk();
    }

    public function test_dashboard_shows_real_counts_without_the_demo_banner(): void
    {
        $this->seed([RoleSeeder::class, SystemSettingSeeder::class, UserSeeder::class, DemoDataSeeder::class]);

        // أُزيل شريط «بيانات عرض تجريبي» من لوحة المدير بطلب المستخدم؛ تبقى صفوف العرض موسومة فرديًا
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('verifications', fn ($v) => $v['total'] === VerificationRequest::count() && $v['failed'] === VerificationRequest::where('status', 'failed')->count())
            ->assertDontSee(__('admin.dashboard.demo_notice'));
    }

    public function test_users_can_be_suspended_and_reactivated_and_the_action_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.status', $user), ['status' => 'suspended'])->assertSessionHas('success');
        $this->assertFalse($user->fresh()->isActive());
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin.user_suspended', 'user_id' => $this->admin->id]);

        $this->actingAs($this->admin)->put(route('admin.users.status', $user), ['status' => 'active'])->assertSessionHas('success');
        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_admin_cannot_suspend_delete_or_demote_their_own_account(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.status', $this->admin), ['status' => 'suspended'])->assertStatus(422);
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertStatus(422);
        $this->actingAs($this->admin)->put(route('admin.users.role', $this->admin), ['role' => 'user'])->assertStatus(422);

        $this->assertTrue($this->admin->fresh()->isAdmin());
        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_a_suspended_admin_loses_access_immediately(): void
    {
        $second = User::factory()->admin()->create();
        $this->actingAs($this->admin)->put(route('admin.users.status', $second), ['status' => 'suspended'])->assertSessionHas('success');

        $this->actingAs($second->fresh())->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_role_can_be_changed_and_only_to_valid_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.role', $user), ['role' => 'superuser'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->put(route('admin.users.role', $user), ['role' => 'admin'])->assertSessionHas('success');

        $this->assertTrue($user->fresh()->isAdmin());
    }

    public function test_deleting_a_user_soft_deletes_the_account_and_removes_their_files(): void
    {
        $user = User::factory()->create();
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $user->id]);
        Storage::disk('private')->put($request->mediaFile->path, $this->jpegBytes());
        $path = $request->mediaFile->path;

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($user);
        Storage::disk('private')->assertMissing($path);

        $this->actingAs($this->admin)->post(route('admin.users.restore', $user->id))->assertRedirect();
        $this->assertNull($user->fresh()->deleted_at);
    }

    public function test_user_list_filters_and_searches(): void
    {
        User::factory()->create(['name' => 'Alice Unique', 'email' => 'alice@example.com']);
        User::factory()->suspended()->create(['name' => 'Bob Other', 'email' => 'bob@example.com']);

        $this->actingAs($this->admin)->get(route('admin.users.index', ['q' => 'alice']))->assertSee('Alice Unique')->assertDontSee('Bob Other');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'suspended']))->assertSee('Bob Other')->assertDontSee('Alice Unique');
    }

    public function test_thresholds_can_be_edited_and_are_validated(): void
    {
        $valid = ['threshold_fake' => 80, 'threshold_real' => 20, 'max_upload_mb' => 50, 'retention_days' => 7, 'contact_email' => 'a@b.co',
            'registration_open' => '1', 'extensions' => ['image' => ['jpg'], 'video' => ['mp4'], 'audio' => ['mp3', 'wav']]];

        $this->actingAs($this->admin)->put(route('admin.settings.update'), $valid)->assertSessionHas('success');

        $this->assertSame(80, SystemSetting::get('threshold_fake'));
        $this->assertSame(20, SystemSetting::get('threshold_real'));
        $this->assertSame(['mp3', 'wav'], SystemSetting::get('allowed_extensions')['audio']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin.settings_updated']);

        foreach ([['threshold_real' => 90, 'threshold_fake' => 70], ['threshold_real' => 70, 'threshold_fake' => 70], ['threshold_fake' => 1], ['max_upload_mb' => 0], ['retention_days' => 0]] as $bad) {
            $this->actingAs($this->admin)->from('/admin/settings')->put(route('admin.settings.update'), array_merge($valid, $bad))->assertSessionHasErrors();
        }
        $this->assertSame(80, SystemSetting::get('threshold_fake'), 'القيم غير الصالحة لا تُحفظ');
    }

    public function test_only_supported_extensions_can_be_enabled(): void
    {
        $payload = ['threshold_fake' => 70, 'threshold_real' => 30, 'max_upload_mb' => 50, 'retention_days' => 7, 'contact_email' => 'a@b.co',
            'extensions' => ['image' => ['jpg', 'php'], 'video' => ['mp4'], 'audio' => ['mp3']]];

        $this->actingAs($this->admin)->put(route('admin.settings.update'), $payload)->assertSessionHasErrors('extensions.image.1');
    }

    public function test_content_editor_saves_bilingual_items_and_escapes_html_on_public_pages(): void
    {
        $payload = ['items' => [
            ['title' => ['ar' => 'سؤال', 'en' => 'Question <script>alert(1)</script>'], 'body' => ['ar' => 'جواب', 'en' => 'Answer <img src=x onerror=alert(1)>']],
            ['title' => ['ar' => '', 'en' => ''], 'body' => ['ar' => 'x', 'en' => 'y']],
        ]];

        $this->actingAs($this->admin)->put(route('admin.content.update', 'faq'), $payload)->assertSessionHas('success');

        $this->assertCount(1, SystemSetting::get('faq_items'), 'الصفوف الفارغة تُحذف');
        auth()->logout();
        $this->get(route('pages.faq'))->assertOk()->assertSee('سؤال');
        $this->get('/lang/en');
        $this->get(route('pages.faq'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_content_endpoint_rejects_unknown_sections(): void
    {
        $this->actingAs($this->admin)->put(route('admin.content.update', 'hacks'), ['items' => []])->assertNotFound();
    }

    public function test_model_versions_can_be_toggled(): void
    {
        $model = ModelVersion::factory()->active()->create();

        $this->actingAs($this->admin)->post(route('admin.models.toggle', $model))->assertSessionHas('success');

        $this->assertFalse($model->fresh()->is_active);
    }

    public function test_importing_a_second_dataset_does_not_erase_the_first_ones_metrics(): void
    {
        // نموذج مركّب واحد (كنموذج الصور) قد يُقاس على أكثر من مجموعة بيانات تفحص قدرات مختلفة له
        $model = ModelVersion::factory()->create(['name' => 'aimv-image-ensemble']);
        $faces = ['dataset' => 'faces v1', 'dataset_key' => 'faces_real_vs_fake', 'confusion_matrix' => ['tp' => 56, 'fp' => 46, 'tn' => 61, 'fn' => 46],
            'accuracy' => 0.56, 'precision' => 56 / 102, 'recall' => 56 / 102, 'false_positive_rate' => 46 / 107, 'false_negative_rate' => 46 / 102, 'f1' => 56 / 102];
        $art = ['dataset' => 'ai art v1', 'dataset_key' => 'ai_art_vs_real', 'confusion_matrix' => ['tp' => 43, 'fp' => 21, 'tn' => 51, 'fn' => 31],
            'accuracy' => 0.6438, 'precision' => 43 / 64, 'recall' => 43 / 74, 'false_positive_rate' => 21 / 72, 'false_negative_rate' => 31 / 74, 'f1' => 0.6232];

        $this->actingAs($this->admin)->post(route('admin.models.metrics', $model), ['metrics_file' => UploadedFile::fake()->createWithContent('faces.json', json_encode($faces))]);
        $this->actingAs($this->admin)->post(route('admin.models.metrics', $model), ['metrics_file' => UploadedFile::fake()->createWithContent('art.json', json_encode($art))]);

        $stored = $model->fresh()->metricsByDataset();
        $this->assertArrayHasKey('faces_real_vs_fake', $stored);
        $this->assertArrayHasKey('ai_art_vs_real', $stored);
        $this->assertEqualsWithDelta(0.5598, $stored['faces_real_vs_fake']['accuracy'], 1e-4);
        $this->assertEqualsWithDelta(0.6438, $stored['ai_art_vs_real']['accuracy'], 1e-4);

        $page = $this->actingAs($this->admin)->get(route('admin.models.index'));
        $page->assertOk()->assertSee('56.0%')->assertSee('64.4%')->assertSee('faces v1')->assertSee('ai art v1');
    }

    public function test_real_evaluation_metrics_can_be_imported_and_are_shown(): void
    {
        $model = ModelVersion::factory()->create(['name' => 'aimv-image-ensemble']);
        $report = [
            'dataset' => 'unit-test-data', 'confusion_matrix' => ['tp' => 45, 'fp' => 8, 'tn' => 42, 'fn' => 5],
            'accuracy' => 0.87, 'precision' => 45 / 53, 'recall' => 0.9, 'false_positive_rate' => 0.16, 'false_negative_rate' => 0.1,
            'f1' => 2 * (45 / 53) * 0.9 / ((45 / 53) + 0.9),
        ];

        $this->actingAs($this->admin)->post(route('admin.models.metrics', $model), ['metrics_file' => UploadedFile::fake()->createWithContent('m.json', json_encode($report))])
            ->assertSessionHas('success');

        $this->assertTrue($model->fresh()->hasMetrics());
        $this->actingAs($this->admin)->get(route('admin.statistics'))->assertSee('87.0%');
        auth()->logout();
        $this->get(route('pages.about'))->assertSee('aimv-image-ensemble')->assertSee('87.0%');
    }

    public function test_fabricated_metrics_are_rejected_and_nothing_is_stored(): void
    {
        $model = ModelVersion::factory()->create();
        $report = ['confusion_matrix' => ['tp' => 45, 'fp' => 8, 'tn' => 42, 'fn' => 5], 'accuracy' => 0.99, 'precision' => 0.99, 'recall' => 0.99,
            'false_positive_rate' => 0.01, 'false_negative_rate' => 0.01, 'f1' => 0.99];

        $this->actingAs($this->admin)->post(route('admin.models.metrics', $model), ['metrics_file' => UploadedFile::fake()->createWithContent('m.json', json_encode($report))])
            ->assertSessionHasErrors('metrics_file');
        $this->actingAs($this->admin)->post(route('admin.models.metrics', $model), ['metrics_file' => UploadedFile::fake()->createWithContent('m.json', 'not json')])
            ->assertSessionHasErrors('metrics_file');

        $this->assertFalse($model->fresh()->hasMetrics());
    }

    public function test_the_about_page_never_shows_a_hard_coded_accuracy(): void
    {
        $this->get(route('pages.about'))->assertOk()->assertSee(__('pages.about.metrics_none'))->assertDontSee('90%');
    }

    public function test_import_command_creates_the_model_version_and_validates(): void
    {
        $report = ['media_type' => 'image', 'dataset' => 'cli-data', 'model' => ['name' => 'aimv-image-ensemble', 'version' => '1.0.0 [a+b]', 'source' => 'https://x', 'license' => 'mit'],
            'confusion_matrix' => ['tp' => 45, 'fp' => 8, 'tn' => 42, 'fn' => 5], 'accuracy' => 0.87, 'precision' => 45 / 53, 'recall' => 0.9,
            'false_positive_rate' => 0.16, 'false_negative_rate' => 0.1, 'f1' => 2 * (45 / 53) * 0.9 / ((45 / 53) + 0.9)];
        $file = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($file, json_encode($report));

        $this->artisan('evaluation:import', ['file' => $file])->assertSuccessful();
        $this->assertTrue(ModelVersion::where('name', 'aimv-image-ensemble')->first()->hasMetrics());

        $report['accuracy'] = 0.5;
        file_put_contents($file, json_encode($report));
        $this->artisan('evaluation:import', ['file' => $file])->assertFailed();
        unlink($file);
    }

    public function test_admin_can_take_down_a_violating_file_but_the_record_stays_for_audit(): void
    {
        $owner = User::factory()->create();
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $owner->id]);
        Storage::disk('private')->put($request->mediaFile->path, $this->jpegBytes());
        VerificationResult::factory()->create(['verification_request_id' => $request->id]);

        $this->actingAs($this->admin)->delete(route('verifications.destroy', $request))->assertRedirect(route('admin.verifications.index'));

        Storage::disk('private')->assertMissing($request->mediaFile->path);
        $this->assertSoftDeleted($request);
        $this->assertDatabaseHas('activity_logs', ['action' => 'verification.taken_down']);
    }

    public function test_verifications_list_shows_failures_and_filters(): void
    {
        $failed = VerificationRequest::factory()->failed()->create(['error_message' => 'service_unavailable']);
        $ok = VerificationRequest::factory()->status(RequestStatus::Completed)->create();

        $this->actingAs($this->admin)->get(route('admin.verifications.index', ['status' => 'failed']))
            ->assertSee($failed->reference_number)->assertDontSee($ok->reference_number)->assertSee(__('ai_errors.service_unavailable'));
    }

    public function test_statistics_export_streams_a_csv_without_personal_data(): void
    {
        $user = User::factory()->create(['email' => 'private-person@example.com']);
        VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $user->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.statistics.export'));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('reference,created_at,user_id', $csv);
        $this->assertStringNotContainsString('private-person@example.com', $csv);
    }

    public function test_activity_log_can_be_filtered(): void
    {
        ActivityLog::factory()->create(['action' => 'auth.login', 'description' => 'فريد-أ']);
        ActivityLog::factory()->create(['action' => 'report.shared', 'description' => 'فريد-ب']);

        $this->actingAs($this->admin)->get(route('admin.activity.index', ['action' => 'report']))
            ->assertSee('فريد-ب')->assertDontSee('فريد-أ');
    }

    public function test_purge_can_be_triggered_manually(): void
    {
        $this->actingAs($this->admin)->post(route('admin.settings.purge'))->assertSessionHas('success');
    }
}
