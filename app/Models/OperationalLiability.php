<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

class OperationalLiability extends Model implements Auditable
{
    use HasFactory, HasAttachments, AuditableTrait;

    protected $fillable = [
        'liability_number',
        'liability_date',
        'creditor',
        'description',
        'fund_id',
        'program_id',
        'amount',
        'amount_settled',
        'due_date',
        'status',
        'settled_disbursement_id',
        'settled_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'liability_date' => 'date',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'amount_settled' => 'decimal:2',
            'settled_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function settledDisbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class, 'settled_disbursement_id');
    }
}
