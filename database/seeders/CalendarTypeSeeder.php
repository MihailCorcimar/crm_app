<?php

namespace Database\Seeders;

use App\Models\CalendarType;
use Illuminate\Database\Seeder;

class CalendarTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CalendarType::query()->upsert(
            [
                ['name' => 'Reuniao', 'status' => 'active'],
                ['name' => 'Chamada', 'status' => 'active'],
                ['name' => 'Visita', 'status' => 'active'],
            ],
            ['name'],
            ['status']
        );
    }
}
