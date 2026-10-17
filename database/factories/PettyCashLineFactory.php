<?php

namespace Database\Factories;

use App\Models\PettyCashCategory;
use App\Models\PettyCashFloat;
use App\Models\PettyCashLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PettyCashLine> */
class PettyCashLineFactory extends Factory
{
    protected $model = PettyCashLine::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'petty_cash_float_id' => PettyCashFloat::factory()->reporting(),
            'category_id' => PettyCashCategory::factory(),
            'spent_on' => now()->toDateString(),
            'amount' => '50000.00',
            'description' => fake()->sentence(4),
            'created_by' => User::factory(),
        ];
    }
}
