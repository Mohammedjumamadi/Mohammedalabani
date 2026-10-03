<?php

namespace Tests\Feature\Database;

use App\Enums\Classification;
use App\Enums\RequestStatus;
use App\Models\ActivityLog;
use App\Models\DetectedIndicator;
use App\Models\MediaFile;
use App\Models\ModelVersion;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_links_user_media_result_and_indicators(): void
    {
        $user = User::factory()->create();
        $request = VerificationRequest::factory()->status(RequestStatus::Completed)->create(['user_id' => $user->id]);
        $model = ModelVersion::factory()->create();
        $result = VerificationResult::factory()->forManipulation(82)->create([
            'verification_request_id' => $request->id,
            'model_version_id' => $model->id,
        ]);
        DetectedIndicator::factory()->count(2)->create(['verification_result_id' => $result->id]);

        $request->refresh();

        $this->assertTrue($request->user->is($user));
        $this->assertTrue($request->mediaFile->user->is($user), 'ملف الوسائط يخص صاحب الطلب');
        $this->assertTrue($request->result->is($result));
        $this->assertSame(Classification::Fake, $request->result->classification);
        $this->assertCount(2, $request->result->indicators);
        $this->assertTrue($result->modelVersion->is($model));
        $this->assertTrue($user->verificationRequests->first()->is($request));
        $this->assertTrue($user->role->slug === Role::USER);
    }

    public function test_admin_factory_state_assigns_the_admin_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse(User::factory()->create()->isAdmin());
    }

    public function test_role_and_status_cannot_be_mass_assigned(): void
    {
        $user = new User;
        $user->fill(['name' => 'X', 'role_id' => 1, 'status' => 'suspended']);

        $this->assertNull($user->role_id);
        $this->assertNull($user->status);
    }

    public function test_reference_numbers_are_generated_unique_and_well_formed(): void
    {
        $requests = VerificationRequest::factory()->count(5)->create();

        $references = $requests->pluck('reference_number');
        $this->assertCount(5, $references->unique());
        foreach ($references as $reference) {
            $this->assertMatchesRegularExpression('/^AMV-\d{4}-[A-HJ-NP-Z2-9]{8}$/', $reference);
        }
    }

    public function test_soft_deleting_a_request_keeps_the_row_and_hides_it_from_queries(): void
    {
        $request = VerificationRequest::factory()->create();

        $request->delete();

        $this->assertSoftDeleted($request);
        $this->assertNull(VerificationRequest::find($request->id));
        $this->assertNotNull(VerificationRequest::withTrashed()->find($request->id));
    }

    public function test_deleting_a_result_removes_its_indicators(): void
    {
        $result = VerificationResult::factory()->create();
        DetectedIndicator::factory()->count(3)->create(['verification_result_id' => $result->id]);

        $result->delete();

        $this->assertDatabaseCount('detected_indicators', 0);
    }

    public function test_a_request_can_only_have_one_result(): void
    {
        $request = VerificationRequest::factory()->create();
        VerificationResult::factory()->create(['verification_request_id' => $request->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        VerificationResult::factory()->create(['verification_request_id' => $request->id]);
    }

    public function test_deleting_a_user_cascades_to_their_media_and_requests(): void
    {
        $request = VerificationRequest::factory()->create();
        $user = $request->user;

        $user->forceDelete();

        $this->assertDatabaseCount('verification_requests', 0);
        $this->assertDatabaseCount('media_files', 0);
    }

    public function test_notifications_and_activity_logs_belong_to_the_user(): void
    {
        $user = User::factory()->create();
        UserNotification::factory()->create(['user_id' => $user->id]);
        UserNotification::factory()->read()->create(['user_id' => $user->id]);
        ActivityLog::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->userNotifications);
        $this->assertSame(1, $user->userNotifications()->unread()->count());
        $this->assertCount(1, $user->activityLogs);
    }

    public function test_activity_log_record_stores_subject_and_no_updated_at(): void
    {
        $user = User::factory()->create();
        $media = MediaFile::factory()->create(['user_id' => $user->id]);

        $log = ActivityLog::record('upload.created', 'رفع ملف', $media, ['size' => 10], $user);

        $this->assertTrue($log->subject->is($media));
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['size' => 10], $log->properties);
        $this->assertNull(ActivityLog::UPDATED_AT);
    }

    public function test_media_file_human_size(): void
    {
        $this->assertSame('512 B', (new MediaFile(['size_bytes' => 512]))->humanSize());
        $this->assertSame('1.50 MB', (new MediaFile(['size_bytes' => 1_572_864]))->humanSize());
    }
}
