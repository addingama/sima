<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_applications', function (Blueprint $table): void {
            $table->enum('beneficiary_scope', ['individual', 'collective'])
                ->default('individual')
                ->after('beneficiary_type');
            $table->unsignedInteger('target_beneficiary_count')->nullable()->after('beneficiary_scope');
            $table->unsignedInteger('actual_beneficiary_count')->nullable()->after('target_beneficiary_count');
            $table->enum('beneficiary_count_method', ['exact', 'estimated'])->nullable()->after('actual_beneficiary_count');
            $table->string('beneficiary_location')->nullable()->after('beneficiary_count_method');
            $table->text('beneficiary_count_notes')->nullable()->after('beneficiary_location');

            $table->index('beneficiary_scope');
        });
    }

    public function down(): void
    {
        Schema::table('grant_applications', function (Blueprint $table): void {
            $table->dropIndex(['beneficiary_scope']);
            $table->dropColumn([
                'beneficiary_scope',
                'target_beneficiary_count',
                'actual_beneficiary_count',
                'beneficiary_count_method',
                'beneficiary_location',
                'beneficiary_count_notes',
            ]);
        });
    }
};
