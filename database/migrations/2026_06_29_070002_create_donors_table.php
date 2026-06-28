<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->id();
            // Tautan opsional ke akun login (Portal Donatur)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['individu', 'lembaga'])->default('individu');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('identity_number')->nullable()->comment('NIK / NPWP');
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donors');
    }
};
