<?php

namespace App\Models;

use Database\Factories\PettyCashCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashCategory extends Model
{
    /** @use HasFactory<PettyCashCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PettyCashLine::class, 'category_id');
    }
}
