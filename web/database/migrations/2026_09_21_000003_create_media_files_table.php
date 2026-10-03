<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('media_type', 10)->index();
            $table->string('original_name');
            // اسم عشوائي آمن؛ لا يُعرض المسار الداخلي للمستخدم أبدًا
            $table->string('stored_name', 100)->nullable()->unique();
            $table->string('disk', 30)->default('private');
            $table->string('path')->nullable();
            $table->string('mime_type', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('file_deleted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
