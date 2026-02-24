<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            ContactRoleSeeder::class,
            VatRateSeeder::class,
            PermissionGroupSeeder::class,
            CompanySettingSeeder::class,
            CalendarTypeSeeder::class,
            CalendarActionSeeder::class,
        ]);
    }
}
