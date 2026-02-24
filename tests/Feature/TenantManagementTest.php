<?php

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;

test('a user can create multiple tenants', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('tenants.store'), [
        'name' => 'Acme One',
        'slug' => 'acme-one',
        'settings' => [
            'brand_name' => 'Acme',
            'brand_primary_color' => '#1F2937',
            'default_user_role' => 'member',
            'allow_member_invites' => false,
        ],
    ])->assertRedirect(route('tenants.index'));

    $this->post(route('tenants.store'), [
        'name' => 'Acme Two',
        'slug' => 'acme-two',
        'settings' => [
            'brand_name' => 'Acme Plus',
            'brand_primary_color' => '#0EA5E9',
            'default_user_role' => 'manager',
            'allow_member_invites' => true,
        ],
    ])->assertRedirect(route('tenants.index'));

    expect(Tenant::query()->count())->toBe(2);

    $tenantIds = Tenant::query()->pluck('id');

    foreach ($tenantIds as $tenantId) {
        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => 'owner',
            'can_create_tenants' => 1,
        ]);
    }
});

test('tenant has independent settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->post(route('tenants.store'), [
        'name' => 'Config Tenant',
        'slug' => 'config-tenant',
        'settings' => [
            'brand_name' => 'Config Brand',
            'brand_primary_color' => '#10B981',
            'default_user_role' => 'manager',
            'allow_member_invites' => true,
        ],
    ])->assertRedirect(route('tenants.index'));

    $tenant = Tenant::query()->firstOrFail();
    $setting = TenantSetting::query()->where('tenant_id', $tenant->id)->first();

    expect($setting)->not->toBeNull();
    expect($setting?->settings)->toBe([
        'brand_name' => 'Config Brand',
        'brand_primary_color' => '#10B981',
        'default_user_role' => 'manager',
        'allow_member_invites' => true,
    ]);
});

test('only owner or authorized users can access tenant', function () {
    $owner = User::factory()->create();
    $authorizedUser = User::factory()->create();
    $outsider = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Secure Tenant',
        'slug' => 'secure-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $this->actingAs($owner)->post(route('tenants.members.store', $tenant), [
        'email' => $authorizedUser->email,
        'can_create_tenants' => true,
    ])->assertRedirect(route('tenants.show', $tenant));

    $this->actingAs($authorizedUser)
        ->get(route('tenants.show', $tenant))
        ->assertOk();

    $this->actingAs($outsider)
        ->get(route('tenants.show', $tenant))
        ->assertForbidden();
});

test('member without permission cannot create tenant but authorized member can', function () {
    $owner = User::factory()->create();
    $limitedMember = User::factory()->create();
    $authorizedMember = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Main Tenant',
        'slug' => 'main-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $tenant->members()->attach($limitedMember->id, [
        'role' => 'member',
        'can_create_tenants' => false,
    ]);

    $tenant->members()->attach($authorizedMember->id, [
        'role' => 'member',
        'can_create_tenants' => true,
    ]);

    $this->actingAs($limitedMember)->post(route('tenants.store'), [
        'name' => 'Blocked Tenant',
        'slug' => 'blocked-tenant',
        'settings' => [
            'brand_name' => 'Blocked',
            'brand_primary_color' => '#F97316',
            'default_user_role' => 'member',
            'allow_member_invites' => false,
        ],
    ])->assertForbidden();

    $this->actingAs($authorizedMember)->post(route('tenants.store'), [
        'name' => 'Allowed Tenant',
        'slug' => 'allowed-tenant',
        'settings' => [
            'brand_name' => 'Allowed',
            'brand_primary_color' => '#6366F1',
            'default_user_role' => 'manager',
            'allow_member_invites' => true,
        ],
    ])->assertRedirect(route('tenants.index'));
});

test('member can add users when allow_member_invites is enabled', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $invitedUser = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Invite Enabled Tenant',
        'slug' => 'invite-enabled-tenant',
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

    TenantSetting::query()->create([
        'tenant_id' => $tenant->id,
        'settings' => [
            'brand_name' => 'Invite Enabled',
            'brand_primary_color' => '#10B981',
            'default_user_role' => 'member',
            'allow_member_invites' => true,
        ],
    ]);

    $this->actingAs($member)->post(route('tenants.members.store', $tenant), [
        'email' => $invitedUser->email,
        'can_create_tenants' => true,
    ])->assertRedirect(route('tenants.show', $tenant));

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitedUser->id,
        'role' => 'member',
        'can_create_tenants' => 0,
    ]);
});

test('member cannot add users when allow_member_invites is disabled', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $invitedUser = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Invite Disabled Tenant',
        'slug' => 'invite-disabled-tenant',
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

    TenantSetting::query()->create([
        'tenant_id' => $tenant->id,
        'settings' => [
            'brand_name' => 'Invite Disabled',
            'brand_primary_color' => '#F97316',
            'default_user_role' => 'member',
            'allow_member_invites' => false,
        ],
    ]);

    $this->actingAs($member)->post(route('tenants.members.store', $tenant), [
        'email' => $invitedUser->email,
        'can_create_tenants' => false,
    ])->assertForbidden();
});

test('adding a member from tenant details uses default user role setting', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Default Role Tenant',
        'slug' => 'default-role-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    TenantSetting::query()->create([
        'tenant_id' => $tenant->id,
        'settings' => [
            'brand_name' => 'Default Role',
            'brand_primary_color' => '#0EA5E9',
            'default_user_role' => 'manager',
            'allow_member_invites' => false,
        ],
    ]);

    $this->actingAs($owner)->post(route('tenants.members.store', $tenant), [
        'email' => $invitedUser->email,
        'can_create_tenants' => false,
    ])->assertRedirect(route('tenants.show', $tenant));

    $this->assertDatabaseHas('tenant_user', [
        'tenant_id' => $tenant->id,
        'user_id' => $invitedUser->id,
        'role' => 'manager',
    ]);
});
