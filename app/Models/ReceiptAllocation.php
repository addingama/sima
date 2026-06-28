<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class ReceiptAllocation extends Model implements Auditable
{
    use HasFactory, HasLedgerEntries, AuditableTrait;

    protected $fillable = [
        'receipt_id',
        'fund_id',
        'program_id',
        'amount',
        'note',
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
            'amount' => 'decimal:2',
            'status' => AllocationStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
