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
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->uuid('session_id')->nullable()->after('user_id');
            $table->index(['tenant_id', 'user_id', 'session_id', 'id'], 'ai_chat_messages_tenant_user_session_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropIndex('ai_chat_messages_tenant_user_session_id_idx');
            $table->dropColumn('session_id');
        });
    }
};

