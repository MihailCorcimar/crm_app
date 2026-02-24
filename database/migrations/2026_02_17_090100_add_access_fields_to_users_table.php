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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('mobile')->nullable()->after('email');
            $table->foreignId('permission_group_id')
                ->nullable()
                ->after('mobile')
                ->constrained('permission_groups')
                ->nullOnDelete();
            $table->string('status', 20)->default('active')->after('permission_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('permission_group_id');
            $table->dropColumn(['mobile', 'status']);
        });
    }
};
