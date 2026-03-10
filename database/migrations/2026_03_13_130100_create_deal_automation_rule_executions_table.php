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
        Schema::create('deal_automation_rule_executions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('deal_automation_rule_id')->constrained('deal_automation_rules')->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_event_id')->nullable()->constrained('calendar_events')->nullOnDelete();
            $table->timestamp('activity_anchor_at');
            $table->timestamp('triggered_at');
            $table->string('status', 20)->default('created');
            $table->string('status_reason', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'deal_automation_rule_id', 'deal_id', 'activity_anchor_at'],
                'deal_automation_rule_executions_unique_anchor'
            );
            $table->index(['tenant_id', 'status', 'triggered_at'], 'deal_automation_rule_executions_tenant_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_automation_rule_executions');
    }
};

