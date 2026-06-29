<?php

namespace App\Models;

use App\Enums\AllocationStatus;
use App\Enums\ReceiptStatus;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasLedgerEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class Receipt extends Model implements Auditable
{
    use AuditableTrait, HasApprovals, HasAttachments, HasFactory, HasLedgerEntries;

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'account_id',
        'donor_id',
        'channel',
        'reference_number',
        'amount',
        'description',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'posted_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'status' => ReceiptStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Total nominal yang sudah dialokasikan (status posted). */
    public function allocatedAmount(): string
    {
        return (string) $this->allocations()
            ->where('status', AllocationStatus::POSTED->value)
            ->sum('amount');
    }

    /** Sisa nominal yang belum dialokasikan. */
    public function unallocatedAmount(): string
    {
        return bcsub((string) $this->amount, $this->allocatedAmount(), 2);
    }
}
