<?php

namespace App\Models;

use App\Enums\BankFeeStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankFee extends Model
{
    use HasAttachments, HasFactory, HasLedgerEntries;

    protected $fillable = [
        'fee_number',
        'fee_date',
        'account_id',
        'fund_id',
        'fee_type',
        'amount',
        'description',
        'status',
        'operational_liability_id',
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
            'fee_date' => 'date',
            'amount' => 'decimal:2',
            'status' => BankFeeStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function operationalLiability(): BelongsTo
    {
        return $this->belongsTo(OperationalLiability::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
