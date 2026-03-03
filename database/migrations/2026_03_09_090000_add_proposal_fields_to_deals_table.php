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
        Schema::table('deals', function (Blueprint $table): void {
            $table->string('proposal_path')->nullable()->after('owner_id');
            $table->string('proposal_original_name')->nullable()->after('proposal_path');
            $table->string('proposal_mime_type')->nullable()->after('proposal_original_name');
            $table->unsignedBigInteger('proposal_size')->nullable()->after('proposal_mime_type');
            $table->timestamp('proposal_uploaded_at')->nullable()->after('proposal_size');
            $table->foreignId('proposal_uploaded_by')
                ->nullable()
                ->after('proposal_uploaded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proposal_uploaded_by');
            $table->dropColumn([
                'proposal_uploaded_at',
                'proposal_size',
                'proposal_mime_type',
                'proposal_original_name',
                'proposal_path',
            ]);
        });
    }
};
