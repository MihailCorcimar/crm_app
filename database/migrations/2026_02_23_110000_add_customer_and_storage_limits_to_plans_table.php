<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'max_customers')) {
                $table->unsignedInteger('max_customers')
                    ->nullable()
                    ->after('max_users');
            }

            if (! Schema::hasColumn('plans', 'storage_limit_gb')) {
                $table->decimal('storage_limit_gb', 8, 2)
                    ->nullable()
                    ->after('max_customers');
            }
        });

        DB::table('plans')
            ->where('code', 'starter')
            ->whereNull('max_customers')
            ->update([
                'max_customers' => 100,
            ]);

        DB::table('plans')
            ->where('code', 'starter')
            ->whereNull('storage_limit_gb')
            ->update([
                'storage_limit_gb' => 2,
            ]);

        DB::table('plans')
            ->where('code', 'growth')
            ->whereNull('max_customers')
            ->update([
                'max_customers' => 5000,
            ]);

        DB::table('plans')
            ->where('code', 'growth')
            ->whereNull('storage_limit_gb')
            ->update([
                'storage_limit_gb' => 20,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'storage_limit_gb')) {
                $table->dropColumn('storage_limit_gb');
            }

            if (Schema::hasColumn('plans', 'max_customers')) {
                $table->dropColumn('max_customers');
            }
        });
    }
};
