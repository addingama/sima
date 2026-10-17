<?php

namespace App\Models;

use App\Enums\PettyCashFundingKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashFunding extends Model
{
    protected $fillable = [
        'petty_cash_float_id',
        'disbursement_id',
        'kind',
        'amount',
        'requested_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => PettyCashFundingKind::class,
            'amount' => 'decimal:2',
        ];
    }

    public function float(): BelongsTo
    {
        return $this->belongsTo(PettyCashFloat::class, 'petty_cash_float_id');
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(Disbursement::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
