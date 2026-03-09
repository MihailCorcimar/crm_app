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
        Schema::create('ai_sales_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('source_type', 40);
            $table->string('action_type', 40);
            $table->string('title', 180);
            $table->text('reason');
            $table->text('next_step')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('priority_score')->default(50);
            $table->timestamp('suggested_for_at')->nullable();
            $table->timestamp('deferred_until')->nullable();
            $table->string('fingerprint', 120);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fingerprint'], 'ai_sales_suggestions_tenant_fingerprint_unique');
            $table->index(['tenant_id', 'user_id', 'status'], 'ai_sales_suggestions_tenant_user_status_idx');
            $table->index(['tenant_id', 'priority_score'], 'ai_sales_suggestions_tenant_priority_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_sales_suggestions');
    }
};
