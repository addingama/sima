<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unique global code + soft delete mencegah reuse kode setelah nonaktif.
     * Validasi unik aktif dipindah ke Form Request (whereNull deleted_at).
     */
    public function up(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->index('code');
        });

        Schema::table('funds', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->index('code');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->index('code');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::table('donors', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('funds', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });
    }
};
