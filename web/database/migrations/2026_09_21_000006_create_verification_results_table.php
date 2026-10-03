<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->unique()->constrained('verification_requests')->cascadeOnDelete();
            $table->string('classification', 15)->index();
            // كلها تقديرات احتمالية من 0 إلى 100 وليست أدلة قطعية
            $table->decimal('confidence_score', 5, 2);
            $table->decimal('authenticity_probability', 5, 2);
            $table->decimal('manipulation_probability', 5, 2);
            $table->string('risk_level', 10);
            $table->text('explanation')->nullable();
            $table->foreignId('model_version_id')->nullable()->constrained('model_versions')->nullOnDelete();
            $table->string('model_name', 150);
            $table->string('model_version', 50);
            $table->decimal('processing_time', 10, 2)->nullable();
            // الحدود المستخدمة وقت التصنيف، لإعادة تفسير النتيجة لاحقًا حتى لو غيّرها المدير
            $table->json('thresholds')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_results');
    }
};
