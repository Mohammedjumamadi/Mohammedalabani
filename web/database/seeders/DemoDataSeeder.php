<?php

namespace Database\Seeders;

use App\Enums\Classification;
use App\Enums\IndicatorSeverity;
use App\Enums\MediaType;
use App\Enums\RequestStatus;
use App\Models\DetectedIndicator;
use App\Models\MediaFile;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use Illuminate\Database\Seeder;

/**
 * بيانات عرض تجريبي فقط لملء اللوحات والرسوم البيانية.
 *
 * تنبيه: هذه القيم ثابتة ومكتوبة يدويًا في هذا الملف، وليست صادرة عن أي نموذج ذكاء اصطناعي حقيقي.
 * تُوسَم كل السجلات بـ is_demo = true ويظهر ذلك للمستخدم. ولا تدخل هذه البيانات في حساب مقاييس الأداء.
 */
class DemoDataSeeder extends Seeder
{
    public const NOTICE = 'بيانات عرض تجريبي وليست نتيجة صادرة عن نموذج ذكاء اصطناعي حقيقي.';

    /** احتمالات التلاعب التجريبية بالتسلسل نفسه في كل مرة. */
    private const MANIPULATION = [8, 12, 22, 64, 35, 88, 93, 17, 5, 78, 46, 71, 29, 55, 95, 10, 41, 83, 3, 60];

    private const MEDIA_CYCLE = ['image', 'image', 'video', 'audio', 'image', 'video', 'image', 'audio'];

    /** رموز المؤشرات المتفق عليها مع خدمة الذكاء الاصطناعي، لكل نوع وسائط. */
    private const INDICATORS = [
        'image' => [
            ['model_deepfake_score', 'model'],
            ['ela_anomaly', 'compression'],
            ['face_edge_artifacts', 'visual'],
        ],
        'video' => [
            ['suspicious_frames', 'temporal'],
            ['temporal_inconsistency', 'temporal'],
            ['face_flicker', 'visual'],
        ],
        'audio' => [
            ['model_voice_score', 'model'],
            ['spectral_artifacts', 'audio'],
            ['unnatural_pauses', 'audio'],
        ],
    ];

    public function run(): void
    {
        $owner = User::where('email', UserSeeder::USER_EMAIL)->firstOrFail();

        // مستخدمون إضافيون لملء لوحة المدير
        $others = User::where('email', 'like', 'demo.user%@aimv.test')->get();
        if ($others->isEmpty()) {
            $others = collect(range(1, 6))->map(fn (int $i) => User::factory()->create([
                'name' => 'مستخدم عرض '.$i,
                'email' => "demo.user{$i}@aimv.test",
            ]));
            User::factory()->suspended()->create(['name' => 'مستخدم معلّق للعرض', 'email' => 'demo.suspended@aimv.test']);
        }

        if (VerificationRequest::where('is_demo', true)->exists()) {
            return; // لا نكرر البيانات
        }

        $users = collect([$owner])->merge($others)->values();

        foreach (range(0, 39) as $i) {
            $user = $i % 2 === 0 ? $owner : $users[$i % $users->count()];
            $type = MediaType::from(self::MEDIA_CYCLE[$i % count(self::MEDIA_CYCLE)]);
            $createdAt = now()->subDays(intdiv($i * 160, 40) + ($i % 3))->subHours($i % 11);

            $media = MediaFile::factory()->create([
                'user_id' => $user->id,
                'media_type' => $type,
                'original_name' => 'demo-'.($i + 1).'.'.match ($type) {
                    MediaType::Image => 'jpg', MediaType::Video => 'mp4', MediaType::Audio => 'mp3'
                },
                'stored_name' => null,
                'path' => null,
                'mime_type' => match ($type) {
                    MediaType::Image => 'image/jpeg', MediaType::Video => 'video/mp4', MediaType::Audio => 'audio/mpeg'
                },
                'extension' => match ($type) {
                    MediaType::Image => 'jpg', MediaType::Video => 'mp4', MediaType::Audio => 'mp3'
                },
                'sha256' => hash('sha256', 'demo-file-'.$i),
                'expires_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            // كل تاسع طلب فاشل، لعرض حالة الفشل في اللوحات
            if ($i % 9 === 8) {
                VerificationRequest::factory()->failed('تعذّر إكمال التحليل (بيانات عرض تجريبي).')->demo()->create([
                    'user_id' => $user->id,
                    'media_file_id' => $media->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                continue;
            }

            $manipulation = self::MANIPULATION[$i % count(self::MANIPULATION)];
            $classification = Classification::fromManipulationProbability($manipulation, 70, 30);

            $request = VerificationRequest::factory()->status($classification->requestStatus())->demo()->create([
                'user_id' => $user->id,
                'media_file_id' => $media->id,
                'started_at' => $createdAt,
                'completed_at' => $createdAt->copy()->addSeconds(20 + ($i % 7) * 15),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $result = VerificationResult::factory()->forManipulation($manipulation)->create([
                'verification_request_id' => $request->id,
                'explanation' => self::NOTICE,
                'model_version_id' => null,
                'model_name' => 'بيانات عرض تجريبي',
                'model_version' => 'demo',
                'processing_time' => 4.5 + ($i % 7) * 3.2,
                'raw_response' => ['demo' => true, 'notice' => self::NOTICE],
                'completed_at' => $request->completed_at,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->createIndicators($result, $type, $classification, $manipulation);
        }
    }

    private function createIndicators(VerificationResult $result, MediaType $type, Classification $classification, int $manipulation): void
    {
        $count = match ($classification) {
            Classification::Fake => 3,
            Classification::Uncertain => 2,
            Classification::Real => 1,
        };

        $severity = match ($classification) {
            Classification::Fake => IndicatorSeverity::High,
            Classification::Uncertain => IndicatorSeverity::Medium,
            Classification::Real => IndicatorSeverity::Info,
        };

        foreach (array_slice(self::INDICATORS[$type->value], 0, $count) as $n => [$code, $category]) {
            DetectedIndicator::factory()->create([
                'verification_result_id' => $result->id,
                'code' => $code,
                'category' => $category,
                'severity' => $severity,
                'score' => max(0, $manipulation - $n * 7),
                'description' => self::NOTICE,
                'timestamp_start' => $type === MediaType::Image ? null : 2.0 + $n * 3.5,
                'timestamp_end' => $type === MediaType::Image ? null : 4.0 + $n * 3.5,
            ]);
        }
    }
}
