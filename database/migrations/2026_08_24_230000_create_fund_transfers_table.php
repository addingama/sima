<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transfer antar Dana Amanah — hanya memindahkan batasan penggunaan dana.
     * Tidak mengubah saldo kas/bank (jurnal 2-leg fund-only).
     */
    public function up(): void
    {
        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->date('transfer_date');
            $table->foreignId('from_fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('to_fund_id')->constrained('funds')->restrictOnDelete();
            $table->decimal('amount', 18, 2);
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('transfer_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
    }
};
