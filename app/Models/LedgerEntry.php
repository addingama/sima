<?php

namespace App\Models;

use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Amanah Ledger — append-only double-entry.
 * Saldo dihitung dari SUM(debit/credit), bukan disimpan statis.
 */
class LedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'ledger_account_type',
        'ledger_account_id',
        'debit',
        'credit',
        'reference',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'ledger_account_type' => LedgerAccountType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Ledger entry bersifat immutable dan tidak dapat diubah.');
        });

        static::deleting(function (): void {
            throw new LogicException('Ledger entry bersifat immutable dan tidak dapat dihapus. Gunakan reversal.');
        });
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'ledger_account_id');
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'ledger_account_id');
    }
}
