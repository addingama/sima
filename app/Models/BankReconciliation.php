<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class BankReconciliation extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    protected $fillable = [
        'account_id',
        'period_start',
        'period_end',
        'statement_balance',
        'system_balance',
        'difference',
        'status',
        'notes',
        'reconciled_at',
        'reconciled_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'statement_balance' => 'decimal:2',
            'system_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'reconciled_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class);
    }
}
