<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $moduleColumns = [
        'entities_create', 'entities_read', 'entities_update', 'entities_delete',
        'people_create', 'people_read', 'people_update', 'people_delete',
        'deals_create', 'deals_read', 'deals_update', 'deals_delete',
        'calendar_create', 'calendar_read', 'calendar_update', 'calendar_delete',
        'products_create', 'products_read', 'products_update', 'products_delete',
        'forms_create', 'forms_read', 'forms_update', 'forms_delete',
        'automations_create', 'automations_read', 'automations_update', 'automations_delete',
        'chat_create', 'chat_read', 'chat_update', 'chat_delete',
        'access_create', 'access_read', 'access_update', 'access_delete',
        'tenants_create', 'tenants_read', 'tenants_update', 'tenants_delete',
        'settings_create', 'settings_read', 'settings_update', 'settings_delete',
        'logs_create', 'logs_read', 'logs_update', 'logs_delete',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = 'permission_groups';

        foreach ($this->moduleColumns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->boolean($column)->default(false);
                });
            }
        }

        $legacyColumns = [
            'menu_a_create', 'menu_a_read', 'menu_a_update', 'menu_a_delete',
            'menu_b_create', 'menu_b_read', 'menu_b_update', 'menu_b_delete',
        ];

        $hasLegacyColumns = collect($legacyColumns)->every(
            fn (string $column): bool => Schema::hasColumn($table, $column)
        );

        if ($hasLegacyColumns) {
            $groupAModules = ['entities', 'people', 'deals', 'calendar', 'products', 'forms', 'automations', 'chat', 'logs'];
            $groupBModules = ['access', 'tenants', 'settings'];
            $actions = ['create', 'read', 'update', 'delete'];

            $updatePayload = [];

            foreach ($groupAModules as $module) {
                foreach ($actions as $action) {
                    $updatePayload["{$module}_{$action}"] = DB::raw("COALESCE(menu_a_{$action}, 0)");
                }
            }

            foreach ($groupBModules as $module) {
                foreach ($actions as $action) {
                    $updatePayload["{$module}_{$action}"] = DB::raw("COALESCE(menu_b_{$action}, 0)");
                }
            }

            DB::table($table)->update($updatePayload);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'menu_a_create',
                    'menu_a_read',
                    'menu_a_update',
                    'menu_a_delete',
                    'menu_b_create',
                    'menu_b_read',
                    'menu_b_update',
                    'menu_b_delete',
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = 'permission_groups';

        $legacyColumns = [
            'menu_a_create',
            'menu_a_read',
            'menu_a_update',
            'menu_a_delete',
            'menu_b_create',
            'menu_b_read',
            'menu_b_update',
            'menu_b_delete',
        ];

        foreach ($legacyColumns as $legacyColumn) {
            if (! Schema::hasColumn($table, $legacyColumn)) {
                Schema::table($table, function (Blueprint $blueprint) use ($legacyColumn): void {
                    $blueprint->boolean($legacyColumn)->default(false);
                });
            }
        }

        $allModuleColumnsExist = collect($this->moduleColumns)->every(
            fn (string $column): bool => Schema::hasColumn($table, $column)
        );

        if ($allModuleColumnsExist) {
            DB::table($table)->update([
                'menu_a_create' => DB::raw('GREATEST(entities_create, people_create, deals_create, calendar_create, products_create, forms_create, automations_create, chat_create, logs_create)'),
                'menu_a_read' => DB::raw('GREATEST(entities_read, people_read, deals_read, calendar_read, products_read, forms_read, automations_read, chat_read, logs_read)'),
                'menu_a_update' => DB::raw('GREATEST(entities_update, people_update, deals_update, calendar_update, products_update, forms_update, automations_update, chat_update, logs_update)'),
                'menu_a_delete' => DB::raw('GREATEST(entities_delete, people_delete, deals_delete, calendar_delete, products_delete, forms_delete, automations_delete, chat_delete, logs_delete)'),
                'menu_b_create' => DB::raw('GREATEST(access_create, tenants_create, settings_create)'),
                'menu_b_read' => DB::raw('GREATEST(access_read, tenants_read, settings_read)'),
                'menu_b_update' => DB::raw('GREATEST(access_update, tenants_update, settings_update)'),
                'menu_b_delete' => DB::raw('GREATEST(access_delete, tenants_delete, settings_delete)'),
            ]);
        }

        foreach ($this->moduleColumns as $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }
    }
};

