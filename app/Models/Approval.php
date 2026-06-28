<?php

namespace App\Models;

use App\Enums\ApprovalAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Approval extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'action',
        'actor_id',
        'actor_role',
        'notes',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'acted_at' => 'datetime',
        ];
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
