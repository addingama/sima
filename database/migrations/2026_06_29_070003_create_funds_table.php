<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dana Amanah = batasan/peruntukan penggunaan uang (restricted funds).
     * Saldo dana TIDAK disimpan di tabel ini; saldo adalah turunan dari SUM(ledger_entries.amount).
     */
    public function up(): void
    {
        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // restricted = terikat niat donatur, unrestricted = dana umum/bebas
            $table->enum('type', ['restricted', 'unrestricted'])->default('restricted');
            // Dana sistem (suspense, biaya admin bank) tidak boleh dihapus / dinonaktifkan
            $table->boolean('is_system')->default(false);
            $table->string('system_key')->nullable()->unique()
                ->comment('Kunci dana sistem: suspense, bank_admin, opening_equity');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
    }
};
