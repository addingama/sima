<?php

namespace App\Models;

use App\Enums\PettyCashTopupRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTopupRequest extends Model
{
    protected $fillable = [
        'petty_cash_float_id',
        'amount',
        'reason',
        'status',
        'funding_id',
        'requested_by',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PettyCashTopupRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function float(): BelongsTo
    {
        return $this->belongsTo(PettyCashFloat::class, 'petty_cash_float_id');
    }

    public function funding(): BelongsTo
    {
        return $this->belongsTo(PettyCashFunding::class, 'funding_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
