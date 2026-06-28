<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liabilitas/kewajiban operasional (register komitmen): gaji, sewa, tagihan
     * yang belum dibayar. Pencatatan obligasi; arus kas sebenarnya terjadi via
     * Pengeluaran (disbursement) yang menyelesaikan liabilitas ini.
     */
    public function up(): void
    {
        Schema::create('operational_liabilities', function (Blueprint $table) {
            $table->id();
            $table->string('liability_number')->unique();
            $table->date('liability_date');
            $table->string('creditor')->comment('Pihak yang harus dibayar');
            $table->text('description')->nullable();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete()
                ->comment('Dana yang akan menanggung kewajiban');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->decimal('amount_settled', 18, 2)->default(0);
            $table->date('due_date')->nullable();

            $table->enum('status', ['outstanding', 'partially_settled', 'settled', 'void'])
                ->default('outstanding');

            // Disbursement yang menyelesaikan kewajiban ini (kasus umum: satu pembayaran penuh).
            $table->foreignId('settled_disbursement_id')->nullable()
                ->constrained('disbursements')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_liabilities');
    }
};
