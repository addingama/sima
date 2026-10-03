<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_applications', function (Blueprint $table) {
            $table->enum('beneficiary_type', ['individual', 'organization'])
                ->default('individual')
                ->after('status');
            $table->string('organization_pic_name')->nullable()->after('recipient_identity_number');
            $table->string('organization_pic_contact')->nullable()->after('organization_pic_name');
            $table->string('organization_pic_relationship')->nullable()->after('organization_pic_contact');
            $table->enum('bank_account_owner_type', ['beneficiary', 'pic'])
                ->default('beneficiary')
                ->after('bank_account_holder');
            $table->string('bank_account_holder_relationship')->nullable()->after('bank_account_owner_type');
            $table->text('bank_account_use_reason')->nullable()->after('bank_account_holder_relationship');
            $table->foreignId('assigned_handover_id')
                ->nullable()
                ->after('assigned_verifier_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('handover_assignment_reason')->nullable()->after('assigned_handover_id');
            $table->string('handover_recipient_name')->nullable()->after('handed_over_by');
            $table->text('handover_recipient_notes')->nullable()->after('handover_recipient_name');

            $table->index('beneficiary_type');
            $table->index('assigned_handover_id');
        });

        Schema::table('grant_applications', function (Blueprint $table) {
            $table->string('recommender_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('grant_applications', function (Blueprint $table) {
            $table->dropForeign(['assigned_handover_id']);
            $table->dropIndex(['assigned_handover_id']);
            $table->dropIndex(['beneficiary_type']);
            $table->dropColumn([
                'beneficiary_type',
                'organization_pic_name',
                'organization_pic_contact',
                'organization_pic_relationship',
                'bank_account_owner_type',
                'bank_account_holder_relationship',
                'bank_account_use_reason',
                'assigned_handover_id',
                'handover_assignment_reason',
                'handover_recipient_name',
                'handover_recipient_notes',
            ]);
        });

        Schema::table('grant_applications', function (Blueprint $table) {
            $table->string('recommender_name')->nullable(false)->change();
        });
    }
};
