<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::query()->upsert(
            [
                ['code' => 'PT', 'name' => 'Portugal'],
                ['code' => 'ES', 'name' => 'Spain'],
                ['code' => 'FR', 'name' => 'France'],
                ['code' => 'DE', 'name' => 'Germany'],
                ['code' => 'GB', 'name' => 'United Kingdom'],
            ],
            ['code'],
            ['name']
        );
    }
}
