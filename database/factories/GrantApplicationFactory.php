<?php

namespace Database\Factories;

use App\Enums\GrantApplicationStatus;
use App\Enums\GrantPaymentMethod;
use App\Models\GrantApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrantApplication> */
class GrantApplicationFactory extends Factory
{
    protected $model = GrantApplication::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $amount = '250000.00';

        return [
            'application_number' => 'BNT/'.now()->year.'/'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => GrantApplicationStatus::DRAFT,
            'recipient_name' => fake()->name(),
            'reason' => 'Bantuan sosial',
            'recommender_name' => fake()->name(),
            'recommended_amount' => $amount,
            'payment_method' => GrantPaymentMethod::CASH,
            'created_by' => User::factory(),
        ];
    }
}
