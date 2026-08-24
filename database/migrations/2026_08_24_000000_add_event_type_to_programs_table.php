<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (! Schema::hasColumn('programs', 'event_type')) {
                $table->enum('event_type', ['planned', 'emergency', 'campaign', 'routine'])
                    ->default('planned')
                    ->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'event_type')) {
                $table->dropColumn('event_type');
            }
        });
    }
};
