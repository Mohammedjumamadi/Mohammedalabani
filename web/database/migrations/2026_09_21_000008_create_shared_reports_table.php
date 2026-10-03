<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shared_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->constrained('verification_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // لا يُحفظ الرمز نصًا واضحًا: البصمة للبحث، والنسخة المشفّرة ليتمكن المالك من نسخ الرابط لاحقًا
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('hide_owner')->default(true);
            $table->boolean('show_preview')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_reports');
    }
};
