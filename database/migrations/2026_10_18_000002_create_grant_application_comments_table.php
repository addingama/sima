<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komentar kartu bantuan, mention, dan akses lihat yang diberikan lewat mention.
     * Akses tidak dicabut saat komentar diedit atau dihapus.
     */
    public function up(): void
    {
        Schema::create('grant_application_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_application_id')->constrained('grant_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grant_application_id', 'id']);
        });

        Schema::create('grant_application_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('grant_application_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
        });

        Schema::create('grant_application_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_application_id')->constrained('grant_applications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 32);
            $table->timestamps();

            $table->unique(['grant_application_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_application_accesses');
        Schema::dropIfExists('grant_application_comment_mentions');
        Schema::dropIfExists('grant_application_comments');
    }
};
