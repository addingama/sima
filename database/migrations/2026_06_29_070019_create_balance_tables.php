<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saldo termaterialisasi (CACHE, bukan source of truth).
     *
     * Tujuan:
     *  - Titik serialisasi (lockForUpdate) agar pengecekan saldo non-negatif AMAN dari
     *    race condition saat banyak transaksi konkuren menyentuh akun/dana yang sama.
     *  - Pembacaan saldo O(1) (tanpa SUM seluruh ledger) untuk ribuan transaksi/hari.
     *
     * Source of truth tetap ledger_entries. Job drift-check (sima:check-balances)
     * membandingkan tabel ini dengan SUM(ledger_entries) untuk mendeteksi penyimpangan.
     */
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('accounts')->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('fund_balances', function (Blueprint $table) {
            $table->foreignId('fund_id')->primary()->constrained('funds')->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_balances');
        Schema::dropIfExists('account_balances');
    }
};
