<?php

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('creating a tenant bootstraps onboarding defaults', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('tenants.store'), [
            'name' => 'Onboarding Tenant',
            'slug' => 'onboarding-tenant',
            'settings' => [
                'brand_name' => 'Onboarding',
                'brand_primary_color' => '#1F2937',
                'default_user_role' => 'member',
                'allow_member_invites' => false,
            ],
        ])
        ->assertRedirect(route('tenants.index'));

    $tenant = Tenant::query()->where('slug', 'onboarding-tenant')->firstOrFail();

    $this->assertDatabaseHas('company_settings', [
        'tenant_id' => $tenant->id,
    ]);
    $this->assertDatabaseHas('countries', [
        'tenant_id' => $tenant->id,
        'code' => 'PT',
    ]);
    $this->assertDatabaseHas('contact_roles', [
        'tenant_id' => $tenant->id,
        'name' => 'Main Contact',
    ]);
    $this->assertDatabaseHas('vat_rates', [
        'tenant_id' => $tenant->id,
        'name' => 'IVA 23%',
    ]);
    $this->assertDatabaseHas('calendar_types', [
        'tenant_id' => $tenant->id,
        'name' => 'General',
    ]);
    $this->assertDatabaseHas('calendar_actions', [
        'tenant_id' => $tenant->id,
        'name' => 'Follow-up',
    ]);
});

test('owner can access onboarding wizard for active tenant', function () {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create([
        'name' => 'Wizard Tenant',
        'slug' => 'wizard-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->get(route('tenants.onboarding.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenantDetails.slug', $tenant->slug)
            ->has('checklist.items')
        );
});

test('authorized user can update onboarding branding and permissions', function () {
    $owner = User::factory()->create();
    $authorized = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Update Tenant',
        'slug' => 'update-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);
    $tenant->members()->attach($authorized->id, [
        'role' => 'member',
        'can_create_tenants' => true,
    ]);

    TenantSetting::query()->create([
        'tenant_id' => $tenant->id,
        'settings' => [],
    ]);

    $authorized->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($authorized)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->put(route('tenants.onboarding.branding'), [
            'brand_name' => 'Updated Brand',
            'brand_primary_color' => '#0EA5E9',
        ])
        ->assertRedirect(route('tenants.onboarding.show'));

    $this->actingAs($authorized)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->put(route('tenants.onboarding.permissions'), [
            'default_user_role' => 'manager',
            'allow_member_invites' => true,
        ])
        ->assertRedirect(route('tenants.onboarding.show'));

    $tenantSettings = TenantSetting::query()->where('tenant_id', $tenant->id)->firstOrFail()->settings;

    expect($tenantSettings['brand_name'] ?? null)->toBe('Updated Brand');
    expect($tenantSettings['brand_primary_color'] ?? null)->toBe('#0EA5E9');
    expect($tenantSettings['default_user_role'] ?? null)->toBe('manager');
    expect($tenantSettings['allow_member_invites'] ?? null)->toBe(true);
});

test('member without tenant onboarding permission cannot update onboarding settings', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Restricted Tenant',
        'slug' => 'restricted-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);
    $tenant->members()->attach($member->id, [
        'role' => 'member',
        'can_create_tenants' => false,
    ]);

    $member->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($member)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->put(route('tenants.onboarding.permissions'), [
            'default_user_role' => 'manager',
            'allow_member_invites' => true,
        ])
        ->assertForbidden();
});

test('adding a member in onboarding uses default user role setting', function () {
    $owner = User::factory()->create();
    $newMember = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Onboarding Role Tenant',
        'slug' => 'onboarding-role-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    TenantSetting::query()->create([
        'tenant_id' => $tenant->id,
        'settings' => [
            'brand_name' => 'Onboarding Role',
            'brand_primary_color' => '#8B5CF6',
            'default_user_role' => 'manager',
            'allow_member_invites' => false,
        ],
    ]);

    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($owner)
        ->withSession(['current_tenant_id' => $tenant->id])
        ->post(route('tenants.onboarding.members'), [
            'email' => $newMember->email,
            'can_create_tenants' => false,
        ])
        ->assertRedirect(route('tenants.onboarding.show'));

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $newMember->id,
        'role' => 'manager',
    ]);
});
