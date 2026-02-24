<?php

namespace Database\Seeders;

use App\Models\CalendarAction;
use Illuminate\Database\Seeder;

class CalendarActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CalendarAction::query()->upsert(
            [
                ['name' => 'Follow-up', 'status' => 'active'],
                ['name' => 'Apresentacao', 'status' => 'active'],
                ['name' => 'Negociacao', 'status' => 'active'],
            ],
            ['name'],
            ['status']
        );
    }
}
