<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliationLine extends Model
{
    protected $fillable = [
        'bank_reconciliation_id',
        'ledger_entry_id',
        'statement_date',
        'statement_ref',
        'statement_amount',
        'is_matched',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_amount' => 'decimal:2',
            'is_matched' => 'boolean',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class);
    }
}
