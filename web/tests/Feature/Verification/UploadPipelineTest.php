<?php

namespace Tests\Feature\Verification;

use App\Enums\Classification;
use App\Enums\RequestStatus;
use App\Models\MediaFile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VerificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakesAiService;
use Tests\TestCase;

class UploadPipelineTest extends TestCase
{
    use FakesAiService, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeStorage();
    }

    private function jpeg(string $name = 'photo.jpg', int $seed = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->jpegBytes($seed));
    }

    private function upload(User $user, UploadedFile $file, array $extra = [])
    {
        return $this->actingAs($user)->post(route('verifications.store'), array_merge([
            'media_type' => 'image', 'file' => $file, 'consent' => '1',
        ], $extra));
    }

    public function test_a_valid_upload_is_stored_hashed_analyzed_and_recorded(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();
        $file = $this->jpeg();

        $response = $this->upload($user, $file);

        $verification = VerificationRequest::firstOrFail();
        $response->assertRedirect(route('verifications.show', $verification));

        $media = $verification->mediaFile;
        $this->assertSame(hash('sha256', $this->jpegBytes(1)), $media->sha256);
        $this->assertNotSame('photo.jpg', $media->stored_name);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.jpg$/', $media->stored_name);
        $this->assertSame('private', $media->disk);
        Storage::disk('private')->assertExists($media->path);
        $this->assertNotNull($media->consented_at);
        $this->assertNotNull($media->expires_at);

        $this->assertSame(RequestStatus::Completed, $verification->status);
        $result = $verification->result;
        $this->assertSame(Classification::Fake, $result->classification);
        $this->assertEqualsWithDelta(18.0, $result->authenticity_probability, 0.01);
        $this->assertSame('test/fake-detector', $result->model_name);
        $this->assertEquals(['fake' => 70, 'real' => 30], $result->thresholds);
        $this->assertCount(2, $result->indicators);
        $this->assertNotNull($result->model_version_id);

        $this->assertDatabaseHas('user_notifications', ['user_id' => $user->id, 'type' => 'verification.completed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'upload.created']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'verification.completed']);
    }

    public function test_the_service_key_is_sent_and_the_file_is_posted_as_multipart(): void
    {
        config(['aimv.ai.key' => 'secret-key-123']);
        $this->fakeAiReturns($this->fakeAnalysis(10));

        $this->upload(User::factory()->create(), $this->jpeg());

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/analyze')
            && $request->hasHeader('X-API-Key', 'secret-key-123')
            && $request->isMultipart());
    }

    public function test_classification_follows_thresholds_edited_by_the_admin(): void
    {
        SystemSetting::put('threshold_fake', 90);
        SystemSetting::put('threshold_real', 50);
        $this->fakeAiReturns($this->fakeAnalysis(82));

        $this->upload(User::factory()->create(), $this->jpeg());

        $result = VerificationRequest::firstOrFail()->result;
        $this->assertSame(Classification::Uncertain, $result->classification);
        $this->assertSame(RequestStatus::NeedsReview, VerificationRequest::first()->status);
    }

    public function test_probabilities_stay_consistent_even_if_the_service_omits_authenticity(): void
    {
        $analysis = $this->fakeAnalysis(25);
        unset($analysis['authenticity_probability'], $analysis['confidence']);
        $this->fakeAiReturns($analysis);

        $this->upload(User::factory()->create(), $this->jpeg());

        $result = VerificationRequest::firstOrFail()->result;
        $this->assertEqualsWithDelta(75.0, $result->authenticity_probability, 0.01);
        $this->assertEqualsWithDelta(75.0, $result->confidence_score, 0.01);
        $this->assertSame(Classification::Real, $result->classification);
    }

    public function test_consent_is_required_before_processing(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis());

        $this->upload(User::factory()->create(), $this->jpeg(), ['consent' => null])
            ->assertSessionHasErrors('consent');

        $this->assertDatabaseCount('media_files', 0);
        Http::assertNothingSent();
    }

    public function test_guests_cannot_upload(): void
    {
        $this->post(route('verifications.store'), ['media_type' => 'image', 'file' => $this->jpeg(), 'consent' => 1])
            ->assertRedirect(route('login'));
    }

    public function test_executable_content_disguised_as_an_image_is_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('photo.jpg', "MZ\x90\x00\x03\x00\x00\x00 fake exe body");

        $this->upload(User::factory()->create(), $fake)->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media_files', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_php_scripts_and_double_extensions_are_rejected(): void
    {
        $user = User::factory()->create();

        foreach (['shell.php', 'shell.php.jpg', 'page.html', 'run.exe'] as $name) {
            $this->upload($user, UploadedFile::fake()->createWithContent($name, $this->jpegBytes()))
                ->assertSessionHasErrors('file');
        }
        $this->upload($user, UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]); ?>'.str_repeat('A', 200)))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_content_must_match_the_selected_media_type(): void
    {
        $this->upload(User::factory()->create(), $this->jpeg('voice.mp3'), ['media_type' => 'audio'])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_corrupt_images_and_empty_files_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->upload($user, UploadedFile::fake()->createWithContent('a.jpg', "\xFF\xD8\xFF\xE0 not really a jpeg"))->assertSessionHasErrors('file');
        $this->upload($user, UploadedFile::fake()->createWithContent('b.jpg', ''))->assertSessionHasErrors('file');
    }

    public function test_the_size_limit_set_by_the_admin_is_enforced(): void
    {
        SystemSetting::put('max_upload_mb', 1);
        $big = UploadedFile::fake()->createWithContent('big.jpg', $this->jpegBytes().str_repeat('0', 1_100_000));

        $this->upload(User::factory()->create(), $big)->assertSessionHasErrors('file');

        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_extensions_can_be_restricted_by_the_admin(): void
    {
        SystemSetting::put('allowed_extensions', ['image' => ['png'], 'video' => ['mp4'], 'audio' => ['mp3']], 'json');
        $this->fakeAiReturns($this->fakeAnalysis());

        $this->upload(User::factory()->create(), $this->jpeg())->assertSessionHasErrors('file');
    }

    public function test_the_original_name_is_sanitised_and_never_used_as_a_path(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(10));
        $file = UploadedFile::fake()->createWithContent('..\\..\\evil name.jpg', $this->jpegBytes());

        $this->upload(User::factory()->create(), $file);

        $media = MediaFile::firstOrFail();
        $this->assertStringNotContainsString('..', $media->path);
        $this->assertStringNotContainsString('evil', $media->path);
        $this->assertStringNotContainsString('/', $media->original_name);
        $this->assertStringNotContainsString('\\', $media->original_name);
    }

    public function test_the_same_file_reuses_the_saved_result_without_calling_the_service_again(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();

        $this->upload($user, $this->jpeg());
        $second = $this->upload($user, $this->jpeg('again.jpg'));

        $requests = VerificationRequest::orderBy('id')->get();
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0]->id, $requests[1]->reused_from_id);
        $this->assertSame(RequestStatus::Completed, $requests[1]->status);
        $this->assertEqualsWithDelta(82.0, $requests[1]->result->manipulation_probability, 0.01);
        $second->assertRedirect(route('verifications.show', $requests[1]));
        Http::assertSentCount(1);
    }

    public function test_reanalysis_can_be_forced(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();

        $this->upload($user, $this->jpeg());
        $this->upload($user, $this->jpeg('again.jpg'), ['reanalyze' => '1']);

        Http::assertSentCount(2);
        $this->assertNull(VerificationRequest::orderByDesc('id')->first()->reused_from_id);
    }

    public function test_reuse_is_reclassified_with_the_current_thresholds(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis(82));
        $user = User::factory()->create();
        $this->upload($user, $this->jpeg());

        SystemSetting::put('threshold_fake', 95);
        $this->upload($user, $this->jpeg('again.jpg'));

        $this->assertSame(Classification::Uncertain, VerificationRequest::orderByDesc('id')->first()->result->classification);
    }

    public function test_upload_page_shows_limits_and_supported_extensions(): void
    {
        $this->fakeAiReturns($this->fakeAnalysis());

        $this->actingAs(User::factory()->create())->get(route('verifications.create'))
            ->assertOk()
            ->assertSee('uploader(', false)
            ->assertSee('consent')
            ->assertSee('csrf-token', false);
    }
}
