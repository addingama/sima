<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alokasi Penerimaan: satu penerimaan dapat dipecah ke beberapa Dana Amanah.
     * Saat di-post: memindahkan dana dari suspense ke fund tujuan (dalam akun yang sama).
     * Total alokasi ter-post per receipt tidak boleh melebihi receipts.amount.
     */
    public function up(): void
    {
        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->text('note')->nullable();

            $table->enum('status', ['posted', 'reversed'])->default('posted');

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['receipt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
    }
};
