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
        $permissions = [];
        foreach (PermissionGroup::permissionColumns() as $column) {
            $permissions[$column] = true;
        }

        PermissionGroup::query()->upsert(
            [
                array_merge([
                    'name' => 'Administrators',
                    'status' => 'active',
                ], $permissions),
            ],
            ['name'],
            array_merge(PermissionGroup::permissionColumns(), ['status'])
        );
    }
}
