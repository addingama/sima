<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrantApplicationAccess extends Model
{
    public const SOURCE_MENTION = 'mention';

    protected $fillable = [
        'grant_application_id',
        'user_id',
        'source',
    ];

    public function grantApplication(): BelongsTo
    {
        return $this->belongsTo(GrantApplication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
