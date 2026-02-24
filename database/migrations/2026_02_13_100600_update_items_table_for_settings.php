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
        Schema::table('items', function (Blueprint $table) {
            $table->string('name')->nullable()->after('code');
            $table->string('reference')->nullable()->after('id');
            $table->foreignId('vat_rate_id')->nullable()->after('price')->constrained('vat_rates');
            $table->string('photo_path')->nullable()->after('vat_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_rate_id');
            $table->dropColumn(['name', 'reference', 'photo_path']);
        });
    }
};
