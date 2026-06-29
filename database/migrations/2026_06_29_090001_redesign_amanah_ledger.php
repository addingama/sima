<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redesign Amanah Ledger: double-entry debit/credit, saldo hanya dari ledger.
     */
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_update');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_delete');
        }

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('fund_balances');
        Schema::dropIfExists('account_balances');

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 50);
            $table->unsignedBigInteger('transaction_id');
            $table->string('ledger_account_type', 50);
            $table->unsignedBigInteger('ledger_account_id');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('reference')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_type', 'transaction_id'], 'ledger_entries_transaction_idx');
            $table->index(['ledger_account_type', 'ledger_account_id'], 'ledger_entries_account_idx');
            $table->index('created_at');
        });

        Schema::enableForeignKeyConstraints();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
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
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_update');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_block_delete');
        }

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ledger_entries');

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('fund_id')->constrained('funds')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->enum('type', [
                'opening', 'receipt', 'allocation_out', 'allocation_in',
                'disbursement', 'bank_fee', 'transfer', 'reversal',
            ]);
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('account_balances', function (Blueprint $table) {
            $table->foreignId('account_id')->primary()->constrained('accounts')->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('fund_balances', function (Blueprint $table) {
            $table->foreignId('fund_id')->primary()->constrained('funds')->cascadeOnDelete();
            $table->decimal('balance', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
};
