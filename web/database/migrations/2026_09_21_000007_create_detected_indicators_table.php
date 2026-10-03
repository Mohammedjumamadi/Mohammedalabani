<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detected_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_result_id')->constrained('verification_results')->cascadeOnDelete();
            // رمز ثابت يصدره النموذج (مثل ela_anomaly)، وتُترجم عناوينه في ملفات اللغة
            $table->string('code', 60)->index();
            $table->string('category', 30);
            $table->string('severity', 10)->default('info');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('description')->nullable();
            // مواضع زمنية بالثواني للفيديو والصوت
            $table->decimal('timestamp_start', 10, 3)->nullable();
            $table->decimal('timestamp_end', 10, 3)->nullable();
            // مسار خاص لصورة الإطار المشبوه (يُقدَّم عبر مسار محمي فقط)
            $table->string('frame_path')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detected_indicators');
    }
};
