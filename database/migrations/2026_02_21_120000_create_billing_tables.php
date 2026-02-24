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
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('billing_cycle_days')->default(30);
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('trial_days')->default(14);
            $table->json('features')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete()
                ->unique();
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->restrictOnDelete();
            $table->string('status', 20)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start_at');
            $table->timestamp('current_period_end_at');
            $table->foreignId('pending_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();
            $table->timestamp('pending_plan_effective_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->integer('last_proration_amount_cents')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('from_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();
            $table->foreignId('to_plan_id')
                ->nullable()
                ->constrained('plans')
                ->nullOnDelete();
            $table->string('change_type', 40);
            $table->timestamp('effective_at');
            $table->integer('proration_amount_cents')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'effective_at'], 'plan_change_logs_tenant_effective_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_change_logs');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
    }
};
