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
        if (! Schema::hasTable('contacts') || ! Schema::hasColumn('contacts', 'entity_id')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id')->nullable()->change();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('contacts') || ! Schema::hasColumn('contacts', 'entity_id')) {
            return;
        }

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropForeign(['entity_id']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->unsignedBigInteger('entity_id')->nullable(false)->change();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->foreign('entity_id')
                ->references('id')
                ->on('entities');
        });
    }
};
