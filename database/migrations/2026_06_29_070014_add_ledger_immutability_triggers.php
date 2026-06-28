<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Hardening audit: cegah UPDATE & DELETE pada ledger_entries di level database.
     * Ledger hanya boleh ditambah (append-only). Pembatalan dilakukan via entry reversal.
     */
    public function up(): void
    {
        // Trigger ini spesifik MySQL/MariaDB. Lewati pada driver lain (mis. SQLite saat testing).
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Ledger entries bersifat immutable: UPDATE tidak diperbolehkan. Gunakan reversal.';
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER ledger_entries_block_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Ledger entries bersifat immutable: DELETE tidak diperbolehkan. Gunakan reversal.';
            END
        SQL);
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_update');
        DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_delete');
    }
};
