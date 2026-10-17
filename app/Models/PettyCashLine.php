<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Database\Factories\PettyCashLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashLine extends Model
{
    /** @use HasFactory<PettyCashLineFactory> */
    use HasAttachments, HasFactory;

    protected $fillable = [
        'petty_cash_float_id',
        'category_id',
        'spent_on',
        'amount',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function float(): BelongsTo
    {
        return $this->belongsTo(PettyCashFloat::class, 'petty_cash_float_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PettyCashCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
