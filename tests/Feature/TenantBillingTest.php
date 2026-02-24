<?php

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('new tenant starts with trial subscription and billing dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), [
            'name' => 'Billing Tenant',
            'slug' => 'billing-tenant',
            'settings' => [
                'brand_name' => 'Billing Tenant',
                'brand_primary_color' => '#1F2937',
                'default_user_role' => 'member',
                'allow_member_invites' => false,
            ],
        ])
        ->assertRedirect(route('tenants.index'));

    $tenant = Tenant::query()->where('slug', 'billing-tenant')->firstOrFail();

    $this->assertDatabaseHas('tenant_subscriptions', [
        'tenant_id' => $tenant->id,
        'status' => 'trialing',
    ]);

    $this->actingAs($user)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenantDetails.slug', $tenant->slug)
            ->where('subscription.status', 'trialing')
            ->where('usage.current_users', 1)
            ->where('usage.current_customers', 0)
            ->where('usage.storage_used_bytes', 0)
            ->has('usage.max_customers')
            ->has('usage.storage_limit_gb')
            ->has('plans')
            ->has('plans.0.max_customers')
            ->has('plans.0.storage_limit_gb')
            ->has('audit_logs')
        );
});

test('upgrade is immediate and stores prorated amount', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Upgrade Tenant',
        'slug' => 'upgrade-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk();

    $growthPlan = Plan::query()->where('code', 'growth')->firstOrFail();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.billing.change-plan', $growthPlan))
        ->assertRedirect(route('tenants.billing.show'));

    $subscription = TenantSubscription::query()
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    expect($subscription->plan_id)->toBe($growthPlan->id);
    expect($subscription->pending_plan_id)->toBeNull();
    expect((int) $subscription->last_proration_amount_cents)->toBeGreaterThan(0);

    $this->assertDatabaseHas('plan_change_logs', [
        'tenant_id' => $tenant->id,
        'change_type' => 'upgrade',
        'to_plan_id' => $growthPlan->id,
    ]);
});

test('downgrade is scheduled and automatically applied on next cycle', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Downgrade Tenant',
        'slug' => 'downgrade-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk();

    $starterPlan = Plan::query()->where('code', 'starter')->firstOrFail();
    $growthPlan = Plan::query()->where('code', 'growth')->firstOrFail();

    TenantSubscription::query()
        ->where('tenant_id', $tenant->id)
        ->update([
            'plan_id' => $growthPlan->id,
            'status' => 'active',
            'trial_ends_at' => CarbonImmutable::now()->subDays(1),
            'current_period_start_at' => CarbonImmutable::now()->subDays(5),
            'current_period_end_at' => CarbonImmutable::now()->addDays(5),
        ]);

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.billing.change-plan', $starterPlan))
        ->assertRedirect(route('tenants.billing.show'));

    $scheduled = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($scheduled->plan_id)->toBe($growthPlan->id);
    expect($scheduled->pending_plan_id)->toBe($starterPlan->id);

    $scheduled->forceFill([
        'pending_plan_effective_at' => CarbonImmutable::now()->subMinute(),
        'current_period_end_at' => CarbonImmutable::now()->subMinute(),
    ])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk();

    $applied = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($applied->plan_id)->toBe($starterPlan->id);
    expect($applied->pending_plan_id)->toBeNull();

    $this->assertDatabaseHas('plan_change_logs', [
        'tenant_id' => $tenant->id,
        'change_type' => 'downgrade_applied',
        'to_plan_id' => $starterPlan->id,
    ]);
});

test('subscription can be canceled at period end and resumed', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Cancel Tenant',
        'slug' => 'cancel-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.billing.cancel'))
        ->assertRedirect(route('tenants.billing.show'));

    $canceled = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($canceled->cancel_at_period_end)->toBeTrue();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.billing.resume'))
        ->assertRedirect(route('tenants.billing.show'));

    $resumed = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    expect($resumed->cancel_at_period_end)->toBeFalse();

    $this->assertDatabaseHas('plan_change_logs', [
        'tenant_id' => $tenant->id,
        'change_type' => 'resume',
    ]);
});

test('premium feature route is gated by current plan', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Premium Tenant',
        'slug' => 'premium-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.show'))
        ->assertOk();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.premium-reports'))
        ->assertRedirect(route('tenants.billing.show'));

    $growthPlan = Plan::query()->where('code', 'growth')->firstOrFail();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.billing.change-plan', $growthPlan))
        ->assertRedirect(route('tenants.billing.show'));

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.billing.premium-reports'))
        ->assertOk();
});
