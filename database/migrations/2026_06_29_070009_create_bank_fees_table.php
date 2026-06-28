<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya Administrasi Bank. Mengurangi saldo akun bank dan dibebankan ke sebuah Dana
     * (umumnya dana sistem "bank_admin" / dana operasional).
     */
    public function up(): void
    {
        Schema::create('bank_fees', function (Blueprint $table) {
            $table->id();
            $table->string('fee_number')->unique();
            $table->date('fee_date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->enum('fee_type', ['admin', 'transfer', 'tax', 'other'])->default('admin');
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

            $table->index('fee_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_fees');
    }
};
