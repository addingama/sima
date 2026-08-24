<?php

namespace App\Models;

use App\Enums\AccountTransferStatus;
use App\Enums\TransactionType;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransfer extends Model
{
    use HasLedgerEntries;

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'from_fund_id',
        'to_fund_id',
        'amount',
        'reference_number',
        'description',
        'status',
        'posted_at',
        'posted_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount' => 'decimal:2',
            'status' => AccountTransferStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function fromFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'from_fund_id');
    }

    public function toFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'to_fund_id');
    }

    public function ledgerTransactionType(): TransactionType
    {
        return TransactionType::FUND_TRANSFER;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
