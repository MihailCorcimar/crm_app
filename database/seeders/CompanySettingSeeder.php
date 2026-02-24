<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanySetting::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'App de Gestao']
        );
    }
}
