<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('active tenant defaults to the first available tenant and is shared in props', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create([
        'name' => 'Alpha',
        'slug' => 'alpha',
        'owner_user_id' => $user->id,
    ]);
    $tenantB = Tenant::factory()->create([
        'name' => 'Beta',
        'slug' => 'beta',
        'owner_user_id' => $user->id,
    ]);

    $tenantA->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);
    $tenantB->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSessionHas('current_tenant_id', $tenantA->id);
    $response->assertInertia(fn (Assert $page) => $page
        ->where('tenant.active.id', $tenantA->id)
        ->where('tenant.active.slug', $tenantA->slug)
        ->has('tenant.available', 2)
    );
});

test('tenant can be switched without re-login and preference is saved', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create([
        'name' => 'Alpha',
        'slug' => 'alpha',
        'owner_user_id' => $user->id,
    ]);
    $tenantB = Tenant::factory()->create([
        'name' => 'Beta',
        'slug' => 'beta',
        'owner_user_id' => $user->id,
    ]);

    $tenantA->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);
    $tenantB->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $this->actingAs($user)->get(route('dashboard'));

    $response = $this->from('/tenants')->post(route('tenants.switch', $tenantB), [
        'remember' => true,
    ]);

    $response->assertRedirect('/tenants');
    $this->assertAuthenticatedAs($user);
    $response->assertSessionHas('current_tenant_id', $tenantB->id);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'current_tenant_id' => $tenantB->id,
    ]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.active.id', $tenantB->id));
});

test('switch is forbidden for unauthorized tenant', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Private Tenant',
        'slug' => 'private-tenant',
        'owner_user_id' => $owner->id,
    ]);

    $tenant->members()->attach($owner->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $this->actingAs($outsider)
        ->post(route('tenants.switch', $tenant), [
            'remember' => true,
        ])
        ->assertForbidden();
});

test('switch can skip preference persistence when remember is false', function () {
    $user = User::factory()->create();

    $tenantA = Tenant::factory()->create([
        'name' => 'Alpha',
        'slug' => 'alpha',
        'owner_user_id' => $user->id,
    ]);
    $tenantB = Tenant::factory()->create([
        'name' => 'Beta',
        'slug' => 'beta',
        'owner_user_id' => $user->id,
    ]);

    $tenantA->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);
    $tenantB->members()->attach($user->id, [
        'role' => 'owner',
        'can_create_tenants' => true,
    ]);

    $user->forceFill(['current_tenant_id' => $tenantA->id])->save();

    $response = $this->actingAs($user)
        ->from('/tenants')
        ->post(route('tenants.switch', $tenantB), [
            'remember' => false,
        ]);

    $response->assertRedirect('/tenants');
    $response->assertSessionHas('current_tenant_id', $tenantB->id);

    expect($user->fresh()->current_tenant_id)->toBe($tenantA->id);
});
