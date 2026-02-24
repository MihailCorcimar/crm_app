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
        Schema::create('permission_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('menu_a_create')->default(false);
            $table->boolean('menu_a_read')->default(false);
            $table->boolean('menu_a_update')->default(false);
            $table->boolean('menu_a_delete')->default(false);
            $table->boolean('menu_b_create')->default(false);
            $table->boolean('menu_b_read')->default(false);
            $table->boolean('menu_b_update')->default(false);
            $table->boolean('menu_b_delete')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_groups');
    }
};
