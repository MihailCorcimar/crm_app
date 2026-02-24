<?php

use App\Models\Country;
use App\Models\Entity;
use App\Models\Tenant;
use App\Models\User;

test('a user cannot access records from another tenant', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $tenantA = Tenant::factory()->create([
        'name' => 'Tenant A',
        'slug' => 'tenant-a',
        'owner_user_id' => $userA->id,
    ]);
    $tenantB = Tenant::factory()->create([
        'name' => 'Tenant B',
        'slug' => 'tenant-b',
        'owner_user_id' => $userB->id,
    ]);

    $tenantA->members()->attach($userA->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $tenantB->members()->attach($userB->id, ['role' => 'owner', 'can_create_tenants' => true]);

    $userA->forceFill(['current_tenant_id' => $tenantA->id])->save();
    $userB->forceFill(['current_tenant_id' => $tenantB->id])->save();

    $countryA = Country::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'code' => 'PT',
        'name' => 'Portugal',
    ]);
    $countryB = Country::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'code' => 'ES',
        'name' => 'Spain',
    ]);

    Entity::withoutGlobalScopes()->create([
        'tenant_id' => $tenantA->id,
        'type' => 'customer',
        'number' => 1,
        'tax_id' => '123456789',
        'name' => 'Customer A',
        'country_id' => $countryA->id,
        'status' => 'active',
    ]);

    $entityFromTenantB = Entity::withoutGlobalScopes()->create([
        'tenant_id' => $tenantB->id,
        'type' => 'customer',
        'number' => 1,
        'tax_id' => '987654321',
        'name' => 'Customer B',
        'country_id' => $countryB->id,
        'status' => 'active',
    ]);

    $this->actingAs($userA)
        ->withSession(['current_tenant_id' => $tenantA->id])
        ->get(route('entities.show', $entityFromTenantB))
        ->assertNotFound();
});

test('api requires x-tenant header matching the active tenant', function () {
    $user = User::factory()->create();

    $tenant = Tenant::factory()->create([
        'name' => 'Tenant API',
        'slug' => 'tenant-api',
        'owner_user_id' => $user->id,
    ]);

    $tenant->members()->attach($user->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $user->forceFill(['current_tenant_id' => $tenant->id])->save();

    $this->actingAs($user)
        ->getJson('/api/tenant/context')
        ->assertStatus(422);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant' => 'wrong-tenant'])
        ->getJson('/api/tenant/context')
        ->assertStatus(403);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant' => $tenant->slug])
        ->getJson('/api/tenant/context')
        ->assertOk()
        ->assertJsonPath('tenant.slug', $tenant->slug);
});
