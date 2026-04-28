<?php

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\Entity;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function dealSetup(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $user->id]);
    $tenant->members()->attach($user->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $user->forceFill(['current_tenant_id' => $tenant->id])->save();

    $country = Country::withoutGlobalScopes()->firstOrCreate(
        ['code' => 'PT'],
        ['name' => 'Portugal', 'tenant_id' => $tenant->id]
    );

    $entity = Entity::withoutGlobalScopes()->create([
        'tenant_id' => $tenant->id,
        'type' => 'customer',
        'number' => 1,
        'tax_id' => fake()->unique()->numerify('#########'),
        'name' => fake()->company(),
        'country_id' => $country->id,
        'status' => 'active',
    ]);

    return compact('user', 'tenant', 'entity');
}

function makeDeal(array $setup, array $overrides = []): Deal
{
    return Deal::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'entity_id' => $setup['entity']->id,
        'title' => 'Test Deal',
        'stage' => Deal::STAGE_LEAD,
        'value' => 1000.00,
        'probability' => 50,
        'owner_id' => $setup['user']->id,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

test('unauthenticated user is redirected from deals index', function (): void {
    $this->get(route('deals.index'))->assertRedirect(route('login'));
});

test('user without tenant is redirected away from deals', function (): void {
    $user = User::factory()->create(['current_tenant_id' => null]);

    $this->actingAs($user)
        ->get(route('deals.index'))
        ->assertRedirect();
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('user can view deals index', function (): void {
    $setup = dealSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('deals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deals/Index')->has('columns'));
});

test('deals index filters by owner', function (): void {
    $setup = dealSetup();

    $otherUser = User::factory()->create(['current_tenant_id' => $setup['tenant']->id]);
    $setup['tenant']->members()->attach($otherUser->id, ['role' => 'member', 'can_create_tenants' => false]);

    makeDeal($setup, ['owner_id' => $setup['user']->id]);
    makeDeal($setup, ['owner_id' => $otherUser->id]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('deals.index', ['owner_id' => $setup['user']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.owner_id', $setup['user']->id));
});

// ---------------------------------------------------------------------------
// Create / Store
// ---------------------------------------------------------------------------

test('user can view deal creation form', function (): void {
    $setup = dealSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('deals.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deals/Create'));
});

test('user can create a deal', function (): void {
    $setup = dealSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.store'), [
            'entity_id' => $setup['entity']->id,
            'title' => 'Novo Negócio',
            'stage' => Deal::STAGE_LEAD,
            'value' => 5000,
            'probability' => 60,
            'owner_id' => $setup['user']->id,
        ])
        ->assertRedirect(route('deals.index'));

    $this->assertDatabaseHas('deals', [
        'tenant_id' => $setup['tenant']->id,
        'title' => 'Novo Negócio',
        'stage' => Deal::STAGE_LEAD,
    ]);
});

test('deal store validates required fields', function (): void {
    $setup = dealSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.store'), [])
        ->assertSessionHasErrors(['title', 'stage', 'owner_id']);
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('user can view a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('deals.show', $deal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('deals/Show')
            ->where('deal.id', $deal->id)
            ->where('deal.title', $deal->title)
        );
});

test('user cannot view deal from another tenant', function (): void {
    $setup = dealSetup();

    $otherUser = User::factory()->create();
    $otherTenant = Tenant::factory()->create(['owner_user_id' => $otherUser->id]);
    $otherTenant->members()->attach($otherUser->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $otherUser->forceFill(['current_tenant_id' => $otherTenant->id])->save();

    $deal = makeDeal($setup);

    // BelongsToTenant global scope hides the deal entirely → 404
    $this->actingAs($otherUser)
        ->withSession(['current_tenant_id' => $otherTenant->id])
        ->get(route('deals.show', $deal))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

test('user can view deal edit form', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('deals.edit', $deal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('deals/Edit'));
});

test('user can update a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->put(route('deals.update', $deal), [
            'entity_id' => $setup['entity']->id,
            'title' => 'Deal Atualizado',
            'stage' => Deal::STAGE_PROPOSAL,
            'value' => 9999,
            'probability' => 75,
            'owner_id' => $setup['user']->id,
        ])
        ->assertRedirect(route('deals.index'));

    $this->assertDatabaseHas('deals', [
        'id' => $deal->id,
        'title' => 'Deal Atualizado',
        'stage' => Deal::STAGE_PROPOSAL,
    ]);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('user can delete a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->delete(route('deals.destroy', $deal))
        ->assertRedirect(route('deals.index'));

    $this->assertDatabaseMissing('deals', ['id' => $deal->id]);
});

// ---------------------------------------------------------------------------
// Update Stage
// ---------------------------------------------------------------------------

test('user can update deal stage via JSON', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup, ['stage' => Deal::STAGE_LEAD]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patchJson(route('deals.stage.update', $deal), ['stage' => Deal::STAGE_PROPOSAL])
        ->assertOk()
        ->assertJsonPath('stage', Deal::STAGE_PROPOSAL);

    $this->assertDatabaseHas('deals', ['id' => $deal->id, 'stage' => Deal::STAGE_PROPOSAL]);
});

test('entering follow up stage activates follow up', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup, ['stage' => Deal::STAGE_LEAD]);

    $setup['entity']->update(['email' => 'cliente@example.com']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patchJson(route('deals.stage.update', $deal), ['stage' => Deal::STAGE_FOLLOW_UP])
        ->assertOk();

    expect($deal->fresh()->follow_up_active)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------------

test('user can add a product to a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $item = Item::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Produto Teste',
        'code' => 'PT-001',
        'price' => 100.00,
        'status' => 'active',
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.products.store', $deal), [
            'item_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 100.00,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('deal_products', [
        'deal_id' => $deal->id,
        'item_id' => $item->id,
        'quantity' => 3,
        'total_value' => 300.00,
    ]);
});

test('adding same product merges quantity', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $item = Item::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Produto Merge',
        'code' => 'PM-001',
        'price' => 50.00,
        'status' => 'active',
    ]);

    $deal->products()->create([
        'tenant_id' => $setup['tenant']->id,
        'item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 50.00,
        'total_value' => 100.00,
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.products.store', $deal), [
            'item_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 50.00,
        ]);

    $this->assertDatabaseHas('deal_products', [
        'deal_id' => $deal->id,
        'item_id' => $item->id,
        'quantity' => 5,
    ]);
    expect(DealProduct::withoutGlobalScopes()->where('deal_id', $deal->id)->count())->toBe(1);
});

test('user can remove a product from a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $item = Item::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Produto Remover',
        'code' => 'PR-001',
        'price' => 10.00,
        'status' => 'active',
    ]);

    $dealProduct = $deal->products()->create([
        'tenant_id' => $setup['tenant']->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 10.00,
        'total_value' => 10.00,
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->delete(route('deals.products.destroy', [$deal, $dealProduct]))
        ->assertRedirect();

    $this->assertDatabaseMissing('deal_products', ['id' => $dealProduct->id]);
});

// ---------------------------------------------------------------------------
// Quick Activity
// ---------------------------------------------------------------------------

test('user can store a quick activity on a deal', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.quick-activity.store', $deal), [
            'activity_type' => 'call',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'owner_id' => $setup['user']->id,
        ])
        ->assertRedirect(route('deals.show', $deal));

    $this->assertDatabaseHas('calendar_events', [
        'eventable_type' => Deal::class,
        'eventable_id' => $deal->id,
        'knowledge' => 'call',
    ]);
});

// ---------------------------------------------------------------------------
// Timeline Feed
// ---------------------------------------------------------------------------

test('timeline feed returns json', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->getJson(route('deals.timeline.feed', $deal))
        ->assertOk()
        ->assertJsonStructure(['timeline', 'generated_at']);
});

// ---------------------------------------------------------------------------
// Follow Up
// ---------------------------------------------------------------------------

test('user can cancel follow up', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup, [
        'stage' => Deal::STAGE_FOLLOW_UP,
        'follow_up_active' => true,
        'follow_up_started_at' => now(),
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.follow-up.cancel', $deal))
        ->assertRedirect();

    expect($deal->fresh()->follow_up_active)->toBeFalse();
});

test('resume follow up fails when deal is not in follow up stage', function (): void {
    $setup = dealSetup();
    $deal = makeDeal($setup, ['stage' => Deal::STAGE_LEAD]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('deals.follow-up.resume', $deal))
        ->assertSessionHasErrors('follow_up');
});
