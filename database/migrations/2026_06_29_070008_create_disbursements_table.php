<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengeluaran. WAJIB memilih Dana Amanah (fund_id) dan Event/Program bila ada.
     * Diposting ke ledger hanya saat status menjadi "approved" (final).
     * Saldo Dana Amanah & saldo akun harus mencukupi (divalidasi di service layer).
     */
    public function up(): void
    {
        Schema::create('disbursements', function (Blueprint $table) {
            $table->id();
            $table->string('disbursement_number')->unique();
            $table->date('disbursement_date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('payee')->nullable()->comment('Penerima pembayaran');
            $table->string('category')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();

            $table->enum('status', [
                'draft',       // dibuat Bendahara, masih bisa diedit
                'submitted',   // diajukan untuk verifikasi
                'verified',    // diverifikasi Verifikator
                'approved',    // disetujui Ketua -> diposting ke ledger
                'rejected',    // ditolak
                'reversed',    // dibatalkan setelah ter-post (reversal)
            ])->default('draft');

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('disbursement_date');
            $table->index('status');
            $table->index('fund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursements');
    }
};
