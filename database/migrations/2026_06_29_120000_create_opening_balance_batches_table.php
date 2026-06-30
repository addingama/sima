<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch posting saldo awal go-live — satu nomor dokumen, banyak baris akun+dana.
     */
    public function up(): void
    {
        Schema::create('opening_balance_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->date('opening_date');
            $table->text('reference')->nullable();
            $table->decimal('total_amount', 18, 2);
            $table->timestamp('posted_at');
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('opening_date');
        });

        Schema::create('opening_balance_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_batch_id')->constrained('opening_balance_batches')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['opening_balance_batch_id', 'line_number']);
            $table->index('account_id');
            $table->index('fund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_lines');
        Schema::dropIfExists('opening_balance_batches');
    }
};
