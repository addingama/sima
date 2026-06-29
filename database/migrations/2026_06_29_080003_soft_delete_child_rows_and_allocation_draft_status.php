<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('expense_fund_sources', function (Blueprint $table) {
            $table->softDeletes();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE receipt_allocations MODIFY status ENUM('draft', 'posted', 'reversed') NOT NULL DEFAULT 'draft'"
            );
        }

        Schema::table('expense_fund_sources', function (Blueprint $table) {
            $table->unique(['disbursement_id', 'fund_id']);
        });
    }

    public function down(): void
    {
        Schema::table('expense_fund_sources', function (Blueprint $table) {
            $table->dropUnique(['disbursement_id', 'fund_id']);
            $table->dropSoftDeletes();
        });

        Schema::table('receipt_allocations', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE receipt_allocations MODIFY status ENUM('posted', 'reversed') NOT NULL DEFAULT 'posted'"
            );
        }
    }
};
