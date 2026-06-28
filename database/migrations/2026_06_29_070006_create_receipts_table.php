<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penerimaan (uang masuk). Saat di-post, dana masuk ke akun dan ditampung sementara
     * di Dana Sistem "suspense" sampai dialokasikan ke Dana Amanah tujuan.
     */
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->date('receipt_date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('donor_id')->nullable()->constrained('donors')->nullOnDelete();
            $table->enum('channel', ['cash', 'transfer', 'qris', 'other'])->default('transfer');
            $table->string('reference_number')->nullable()->comment('No. referensi bank/transaksi');
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();

            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');

            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('receipt_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
