<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Buku Besar (ledger) — append-only / immutable.
 * Model ini sengaja TIDAK mengizinkan update maupun delete.
 */
class LedgerEntry extends Model
{
    protected $fillable = [
        'entry_date',
        'account_id',
        'fund_id',
        'program_id',
        'amount',
        'type',
        'source_type',
        'source_id',
        'reversal_of_id',
        'memo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'amount' => 'decimal:2',
            'type' => LedgerType::class,
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
