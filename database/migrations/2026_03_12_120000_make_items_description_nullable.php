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
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'description')) {
            return;
        }

        Schema::table('items', function (Blueprint $table): void {
            $table->string('description', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('items') || ! Schema::hasColumn('items', 'description')) {
            return;
        }

        DB::table('items')
            ->whereNull('description')
            ->update(['description' => '']);

        Schema::table('items', function (Blueprint $table): void {
            $table->string('description', 255)->nullable(false)->change();
        });
    }
};

