<?php

namespace Database\Seeders;

use App\Models\Fund;
use Illuminate\Database\Seeder;

class SystemFundSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('sima.system_funds') as $fund) {
            Fund::updateOrCreate(
                ['system_key' => $fund['system_key']],
                [
                    'code' => $fund['code'],
                    'name' => $fund['name'],
                    'description' => $fund['description'] ?? null,
                    'type' => $fund['type'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
