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
        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('role', 20);
            $table->text('text');
            $table->string('intent', 50)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('links')->nullable();
            $table->json('context_data')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at'], 'ai_chat_messages_tenant_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};

