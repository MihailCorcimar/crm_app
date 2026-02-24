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
    private array $tables = [
        'countries',
        'contact_roles',
        'entities',
        'contacts',
        'items',
        'vat_rates',
        'proposals',
        'proposal_lines',
        'orders',
        'order_lines',
        'supplier_orders',
        'supplier_order_lines',
        'supplier_invoices',
        'activity_logs',
        'company_settings',
        'calendar_types',
        'calendar_actions',
        'calendar_events',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            $this->ensureTenantColumnAndConstraint($tableName);
        }

        $tenantId = DB::table('tenants')->min('id');
        if ($tenantId !== null) {
            foreach ($this->tables as $tableName) {
                DB::table($tableName)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenantId]);
            }
        }

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropUnique('countries_code_unique');
            $table->unique(['tenant_id', 'code'], 'countries_tenant_code_unique');
        });

        Schema::table('contact_roles', function (Blueprint $table): void {
            $table->dropUnique('contact_roles_name_unique');
            $table->unique(['tenant_id', 'name'], 'contact_roles_tenant_name_unique');
        });

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropUnique('entities_number_unique');
            $table->dropUnique('entities_tax_id_unique');
            $table->unique(['tenant_id', 'number'], 'entities_tenant_number_unique');
            $table->unique(['tenant_id', 'tax_id'], 'entities_tenant_tax_id_unique');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_number_unique');
            $table->unique(['tenant_id', 'number'], 'contacts_tenant_number_unique');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropUnique('items_code_unique');
            $table->dropUnique('items_reference_unique');
            $table->unique(['tenant_id', 'code'], 'items_tenant_code_unique');
            $table->unique(['tenant_id', 'reference'], 'items_tenant_reference_unique');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropUnique('proposals_number_unique');
            $table->unique(['tenant_id', 'number'], 'proposals_tenant_number_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_number_unique');
            $table->unique(['tenant_id', 'number'], 'orders_tenant_number_unique');
        });

        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropUnique('supplier_orders_number_unique');
            $table->unique(['tenant_id', 'number'], 'supplier_orders_tenant_number_unique');
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropUnique('supplier_invoices_number_unique');
            $table->unique(['tenant_id', 'number'], 'supplier_invoices_tenant_number_unique');
        });

        Schema::table('calendar_types', function (Blueprint $table): void {
            $table->dropUnique('calendar_types_name_unique');
            $table->unique(['tenant_id', 'name'], 'calendar_types_tenant_name_unique');
        });

        Schema::table('calendar_actions', function (Blueprint $table): void {
            $table->dropUnique('calendar_actions_name_unique');
            $table->unique(['tenant_id', 'name'], 'calendar_actions_tenant_name_unique');
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            $table->unique(['tenant_id'], 'company_settings_tenant_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropUnique('company_settings_tenant_unique');
        });

        Schema::table('calendar_actions', function (Blueprint $table): void {
            $table->dropUnique('calendar_actions_tenant_name_unique');
            $table->unique('name', 'calendar_actions_name_unique');
        });

        Schema::table('calendar_types', function (Blueprint $table): void {
            $table->dropUnique('calendar_types_tenant_name_unique');
            $table->unique('name', 'calendar_types_name_unique');
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropUnique('supplier_invoices_tenant_number_unique');
            $table->unique('number', 'supplier_invoices_number_unique');
        });

        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropUnique('supplier_orders_tenant_number_unique');
            $table->unique('number', 'supplier_orders_number_unique');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_tenant_number_unique');
            $table->unique('number', 'orders_number_unique');
        });

        Schema::table('proposals', function (Blueprint $table): void {
            $table->dropUnique('proposals_tenant_number_unique');
            $table->unique('number', 'proposals_number_unique');
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->dropUnique('items_tenant_code_unique');
            $table->dropUnique('items_tenant_reference_unique');
            $table->unique('code', 'items_code_unique');
            $table->unique('reference', 'items_reference_unique');
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_tenant_number_unique');
            $table->unique('number', 'contacts_number_unique');
        });

        Schema::table('entities', function (Blueprint $table): void {
            $table->dropUnique('entities_tenant_number_unique');
            $table->dropUnique('entities_tenant_tax_id_unique');
            $table->unique('number', 'entities_number_unique');
            $table->unique('tax_id', 'entities_tax_id_unique');
        });

        Schema::table('contact_roles', function (Blueprint $table): void {
            $table->dropUnique('contact_roles_tenant_name_unique');
            $table->unique('name', 'contact_roles_name_unique');
        });

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropUnique('countries_tenant_code_unique');
            $table->unique('code', 'countries_code_unique');
        });

        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            $foreignKeyName = "{$tableName}_tenant_id_foreign";
            $indexName = "{$tableName}_tenant_id_index";

            if ($this->hasForeignKey($tableName, $foreignKeyName)) {
                Schema::table($tableName, function (Blueprint $table) use ($foreignKeyName): void {
                    $table->dropForeign($foreignKeyName);
                });
            }

            if ($this->hasIndex($tableName, $indexName)) {
                Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('tenant_id');
            });
        }
    }

    private function ensureTenantColumnAndConstraint(string $tableName): void
    {
        if (! Schema::hasColumn($tableName, 'tenant_id')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')
                    ->nullable()
                    ->after('id');
            });
        }

        $this->dropLegacyNumericTenantConstraint($tableName);

        $indexName = "{$tableName}_tenant_id_index";
        if (! $this->hasIndex($tableName, $indexName)) {
            Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
                $table->index('tenant_id', $indexName);
            });
        }

        $foreignKeyName = "{$tableName}_tenant_id_foreign";
        if (! $this->hasForeignKey($tableName, $foreignKeyName)) {
            Schema::table($tableName, function (Blueprint $table) use ($foreignKeyName): void {
                $table->foreign('tenant_id', $foreignKeyName)
                    ->references('id')
                    ->on('tenants')
                    ->nullOnDelete();
            });
        }
    }

    private function dropLegacyNumericTenantConstraint(string $tableName): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->hasForeignKey($tableName, '1')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign('1');
            });
        }

        if ($this->hasIndex($tableName, '1')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex('1');
            });
        }
    }

    private function hasIndex(string $tableName, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $tableName)
                ->where('index_name', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            try {
                $indexes = DB::select("PRAGMA index_list('{$tableName}')");
            } catch (\Throwable) {
                return false;
            }

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function hasForeignKey(string $tableName, string $foreignKeyName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $tableName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', $foreignKeyName)
            ->exists();
    }
};
