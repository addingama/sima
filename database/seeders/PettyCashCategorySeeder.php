<?php

namespace Database\Seeders;

use App\Models\PettyCashCategory;
use Illuminate\Database\Seeder;

class PettyCashCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'INTERNET', 'name' => 'Internet', 'sort_order' => 10],
            ['code' => 'ATK', 'name' => 'ATK', 'sort_order' => 20],
            ['code' => 'TRANSPORT', 'name' => 'Transport', 'sort_order' => 30],
            ['code' => 'KONSUMSI', 'name' => 'Konsumsi', 'sort_order' => 40],
            ['code' => 'SEWA', 'name' => 'Sewa', 'sort_order' => 50],
            ['code' => 'LAINNYA', 'name' => 'Lainnya', 'sort_order' => 90],
        ];

        foreach ($rows as $row) {
            PettyCashCategory::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]
            );
        }
    }
}
