<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('petty_cash_floats', function (Blueprint $table) {
            $table->id();
            $table->string('float_number', 32)->unique();
            $table->string('title');
            $table->string('purpose', 32); // operational | event
            $table->string('status', 32)->default('draft');
            $table->foreignId('custodian_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('return_receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->decimal('returned_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('funded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'purpose']);
            $table->index(['custodian_user_id', 'status']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::create('petty_cash_fundings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_float_id')->constrained('petty_cash_floats')->cascadeOnDelete();
            $table->foreignId('disbursement_id')->unique()->constrained('disbursements')->restrictOnDelete();
            $table->string('kind', 16); // initial | topup
            $table->decimal('amount', 18, 2);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['petty_cash_float_id', 'kind']);
        });

        Schema::create('petty_cash_topup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_float_id')->constrained('petty_cash_floats')->cascadeOnDelete();
            $table->decimal('amount', 18, 2);
            $table->text('reason');
            $table->string('status', 16)->default('pending'); // pending | funded | rejected | cancelled
            $table->foreignId('funding_id')->nullable()->constrained('petty_cash_fundings')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['petty_cash_float_id', 'status']);
        });

        Schema::create('petty_cash_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_float_id')->constrained('petty_cash_floats')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('petty_cash_categories')->restrictOnDelete();
            $table->date('spent_on');
            $table->decimal('amount', 18, 2);
            $table->string('description');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['petty_cash_float_id', 'spent_on']);
            $table->index(['category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_lines');
        Schema::dropIfExists('petty_cash_topup_requests');
        Schema::dropIfExists('petty_cash_fundings');
        Schema::dropIfExists('petty_cash_floats');
        Schema::dropIfExists('petty_cash_categories');
    }
};
