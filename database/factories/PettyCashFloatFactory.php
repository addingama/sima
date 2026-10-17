<?php

namespace Database\Factories;

use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFloatStatus;
use App\Models\PettyCashFloat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PettyCashFloat> */
class PettyCashFloatFactory extends Factory
{
    protected $model = PettyCashFloat::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        return [
            'float_number' => 'KSK/'.now()->year.'/'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Operasional '.fake()->monthName(),
            'purpose' => PettyCashFloatPurpose::OPERATIONAL,
            'status' => PettyCashFloatStatus::DRAFT,
            'custodian_user_id' => User::factory(),
            'period_start' => $start,
            'period_end' => $end,
            'returned_amount' => '0.00',
            'created_by' => User::factory(),
        ];
    }

    public function reporting(): static
    {
        return $this->state(fn () => [
            'status' => PettyCashFloatStatus::REPORTING,
            'funded_at' => now(),
        ]);
    }

    public function event(): static
    {
        return $this->state(fn () => [
            'purpose' => PettyCashFloatPurpose::EVENT,
            'period_start' => null,
            'period_end' => null,
            'title' => 'Event '.fake()->words(2, true),
        ]);
    }
}
