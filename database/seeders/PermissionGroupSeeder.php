<?php

namespace Database\Seeders;

use App\Models\PermissionGroup;
use Illuminate\Database\Seeder;

class PermissionGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PermissionGroup::query()->upsert(
            [
                [
                    'name' => 'Administrators',
                    'menu_a_create' => true,
                    'menu_a_read' => true,
                    'menu_a_update' => true,
                    'menu_a_delete' => true,
                    'menu_b_create' => true,
                    'menu_b_read' => true,
                    'menu_b_update' => true,
                    'menu_b_delete' => true,
                    'status' => 'active',
                ],
            ],
            ['name'],
            [
                'menu_a_create',
                'menu_a_read',
                'menu_a_update',
                'menu_a_delete',
                'menu_b_create',
                'menu_b_read',
                'menu_b_update',
                'menu_b_delete',
                'status',
            ]
        );
    }
}
