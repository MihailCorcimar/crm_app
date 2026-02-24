<?php

namespace Database\Seeders;

use App\Models\ContactRole;
use Illuminate\Database\Seeder;

class ContactRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ContactRole::query()->upsert(
            [
                ['name' => 'Manager'],
                ['name' => 'Purchasing'],
                ['name' => 'Finance'],
                ['name' => 'Sales'],
            ],
            ['name'],
            ['name']
        );
    }
}
