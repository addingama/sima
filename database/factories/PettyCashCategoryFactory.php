<?php

namespace Database\Factories;

use App\Models\PettyCashCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PettyCashCategory> */
class PettyCashCategoryFactory extends Factory
{
    protected $model = PettyCashCategory::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('CAT???')),
            'name' => fake()->words(2, true),
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
