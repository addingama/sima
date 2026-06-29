<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_fees', function (Blueprint $table) {
            $table->foreign('operational_liability_id')
                ->references('id')->on('operational_liabilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_fees', function (Blueprint $table) {
            $table->dropForeign(['operational_liability_id']);
        });
    }
};
