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
        Schema::create('deal_automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name', 120);
            $table->string('trigger_type', 40)->default('deal_inactivity');
            $table->unsignedSmallInteger('inactivity_days')->default(5);
            $table->string('action_type', 40)->default('create_calendar_activity');
            $table->string('activity_type', 20)->default('task');
            $table->unsignedSmallInteger('activity_due_in_days')->default(0);
            $table->string('activity_priority', 20)->default('medium');
            $table->string('activity_title_template', 180);
            $table->text('activity_description_template')->nullable();
            $table->boolean('notify_internal')->default(true);
            $table->string('notification_message', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'deal_automation_rules_tenant_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_automation_rules');
    }
};

