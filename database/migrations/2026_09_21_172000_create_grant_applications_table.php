<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan bantuan (kasus per penerima). Bukan transaksi ledger.
     * Uang keluar hanya lewat pengeluaran tertaut (disbursement_id) 1:1.
     */
    public function up(): void
    {
        Schema::create('grant_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();

            $table->enum('status', [
                'draft',
                'verification',
                'pending_approval',
                'approved',
                'completed',
                'rejected',
            ])->default('draft');

            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('recipient_identity_number')->nullable()->comment('NIK atau nomor identitas');

            $table->decimal('recommended_amount', 18, 2);
            $table->decimal('verified_amount', 18, 2)->nullable();
            $table->decimal('approved_amount', 18, 2)->nullable();

            $table->text('reason');
            $table->string('recommender_name');
            $table->string('recommender_contact')->nullable();

            $table->enum('payment_method', ['cash', 'transfer'])->default('cash');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();

            $table->text('notes')->nullable();
            $table->text('verifier_notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->foreignId('assigned_verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disbursement_id')->nullable()->unique()->constrained('disbursements')->nullOnDelete();

            $table->timestamp('sent_to_verification_at')->nullable();
            $table->foreignId('sent_to_verification_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_for_approval_at')->nullable();
            $table->foreignId('submitted_for_approval_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('handed_over_on')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->foreignId('handed_over_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('assigned_verifier_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_applications');
    }
};
