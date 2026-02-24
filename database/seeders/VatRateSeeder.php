<?php

namespace Database\Seeders;

use App\Models\VatRate;
use Illuminate\Database\Seeder;

class VatRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        VatRate::query()->upsert(
            [
                ['name' => 'Taxa reduzida', 'rate' => 6.00, 'status' => 'active'],
                ['name' => 'Taxa intermédia', 'rate' => 13.00, 'status' => 'active'],
                ['name' => 'Taxa normal', 'rate' => 23.00, 'status' => 'active'],
            ],
            ['name'],
            ['rate', 'status']
        );
    }
}
