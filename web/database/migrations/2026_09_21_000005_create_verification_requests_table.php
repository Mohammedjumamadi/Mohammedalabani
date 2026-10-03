<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('stage', 50)->nullable();
            // عند العثور على ملف سبق تحليله بالبصمة نفسها وبنسخة النموذج نفسها
            $table->foreignId('reused_from_id')->nullable()->constrained('verification_requests')->nullOnDelete();
            // رسالة آمنة تعرض للمستخدم؛ التفاصيل التقنية تُسجَّل في السجلات فقط
            $table->string('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('is_demo')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
    }
};
