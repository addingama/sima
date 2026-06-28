<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sumber Dana sebuah Pengeluaran.
     * Satu pengeluaran dapat ditarik dari beberapa Dana Amanah; SUM(amount) = disbursements.amount.
     * Setiap baris menghasilkan satu leg ledger (account -, fund -) saat pengeluaran di-approve.
     */
    public function up(): void
    {
        Schema::create('expense_fund_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('disbursement_id')->constrained('disbursements')->cascadeOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('disbursement_id');
            $table->index('fund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_fund_sources');
    }
};
