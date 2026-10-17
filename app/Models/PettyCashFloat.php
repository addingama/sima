<?php

namespace App\Models;

use App\Enums\DisbursementStatus;
use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFloatStatus;
use Database\Factories\PettyCashFloatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class PettyCashFloat extends Model implements Auditable
{
    /** @use HasFactory<PettyCashFloatFactory> */
    use AuditableTrait, HasFactory;

    protected $fillable = [
        'float_number',
        'title',
        'purpose',
        'status',
        'custodian_user_id',
        'program_id',
        'period_start',
        'period_end',
        'return_receipt_id',
        'returned_amount',
        'notes',
        'funded_at',
        'closed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PettyCashFloatPurpose::class,
            'status' => PettyCashFloatStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'returned_amount' => 'decimal:2',
            'funded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnReceipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class, 'return_receipt_id');
    }

    public function fundings(): HasMany
    {
        return $this->hasMany(PettyCashFunding::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PettyCashLine::class);
    }

    public function topupRequests(): HasMany
    {
        return $this->hasMany(PettyCashTopupRequest::class);
    }

    /** Total cair dari DSB funding yang sudah approved. */
    public function fundedTotal(): string
    {
        $total = '0.00';

        $this->loadMissing(['fundings.disbursement']);

        foreach ($this->fundings as $funding) {
            if ($funding->disbursement?->status !== DisbursementStatus::APPROVED) {
                continue;
            }
            $total = bcadd($total, (string) $funding->amount, 2);
        }

        return $total;
    }

    public function spentTotal(): string
    {
        $total = '0.00';
        foreach ($this->lines()->pluck('amount') as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    public function remainingTotal(): string
    {
        return bcsub(
            bcsub($this->fundedTotal(), $this->spentTotal(), 2),
            (string) $this->returned_amount,
            2
        );
    }
}
