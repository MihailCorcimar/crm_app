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
        Schema::create('ai_sales_suggestion_feedback', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_sales_suggestion_id')
                ->nullable()
                ->constrained('ai_sales_suggestions')
                ->nullOnDelete();
            $table->string('action_type', 40);
            $table->string('decision', 20);
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'action_type'], 'ai_sales_feedback_tenant_user_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_sales_suggestion_feedback');
    }
};
