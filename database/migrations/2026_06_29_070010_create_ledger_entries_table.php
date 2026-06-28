<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LEDGER (Buku Besar) — sumber kebenaran tunggal, bersifat append-only / immutable.
     *
     * Setiap baris mencatat pergerakan uang pada DUA dimensi sekaligus:
     *   - account_id : di kas/bank mana uang berada
     *   - fund_id    : peruntukan (Dana Amanah) uang tersebut
     *
     * Invariant:
     *   - Saldo akun  = SUM(amount) per account_id  (harus >= 0)
     *   - Saldo dana  = SUM(amount) per fund_id      (harus >= 0)
     *   - Total saldo semua akun == total saldo semua dana
     *
     * amount bertanda: positif = bertambah, negatif = berkurang.
     * Tidak ada UPDATE/DELETE — pembatalan dilakukan via entry reversal (negasi).
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();

            $table->decimal('amount', 18, 2)->comment('Bertanda: + masuk, - keluar');

            $table->enum('type', [
                'opening',        // saldo awal
                'receipt',        // penerimaan masuk ke suspense
                'allocation_out', // keluar dari suspense
                'allocation_in',  // masuk ke dana tujuan
                'disbursement',   // pengeluaran
                'bank_fee',       // biaya admin bank
                'transfer',       // mutasi antar akun
                'reversal',       // negasi dari entry lain
            ]);

            // Tautan polymorphic ke transaksi sumber (receipt, disbursement, dst.)
            $table->nullableMorphs('source');

            // Bila entry ini adalah reversal, menunjuk ke entry asli yang dinegasi
            $table->foreignId('reversal_of_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();

            $table->text('memo')->nullable();
            // restrictOnDelete: user pembuat ledger tidak boleh dihapus (jejak audit + immutability)
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'entry_date']);
            $table->index(['fund_id', 'entry_date']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
