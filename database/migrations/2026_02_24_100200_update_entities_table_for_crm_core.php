<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            if (! Schema::hasColumn('entities', 'vat')) {
                $table->string('vat', 30)->nullable()->after('tax_id');
            }
        });

        if (Schema::hasColumn('entities', 'tenant_id')) {
            Schema::table('entities', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'vat'], 'entities_tenant_vat_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table): void {
            $table->dropUnique('entities_tenant_vat_unique');
        });

        if (Schema::hasColumn('entities', 'vat')) {
            Schema::table('entities', function (Blueprint $table): void {
                $table->dropColumn('vat');
            });
        }
    }
};
