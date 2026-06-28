<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('statement_balance', 18, 2)->comment('Saldo menurut rekening koran');
            $table->decimal('system_balance', 18, 2)->comment('Saldo menurut sistem (snapshot)');
            $table->decimal('difference', 18, 2)->default(0);
            $table->enum('status', ['draft', 'in_progress', 'completed'])->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
    }
};
