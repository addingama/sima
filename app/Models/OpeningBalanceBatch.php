<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningBalanceBatch extends Model
{
    use HasLedgerEntries;

    protected $fillable = [
        'batch_number',
        'opening_date',
        'reference',
        'total_amount',
        'posted_at',
        'posted_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_date' => 'date',
            'total_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningBalanceLine::class)->orderBy('line_number');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerTransactionType(): TransactionType
    {
        return TransactionType::OPENING;
    }
}
