<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baris pencocokan rekonsiliasi: menautkan baris rekening koran dengan ledger entry sistem.
     */
    public function up(): void
    {
        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')
                ->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()
                ->constrained('ledger_entries')->nullOnDelete();
            $table->date('statement_date')->nullable();
            $table->string('statement_ref')->nullable();
            $table->decimal('statement_amount', 18, 2)->nullable();
            $table->boolean('is_matched')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('bank_reconciliation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
    }
};
