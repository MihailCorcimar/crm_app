<?php

use App\Models\Contact;
use App\Models\ContactRole;
use App\Models\Country;
use App\Models\Entity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function contactSetup(): array
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

    $role = ContactRole::withoutGlobalScopes()->firstOrCreate(
        ['name' => 'Decisor'],
        ['tenant_id' => $tenant->id]
    );

    return compact('user', 'tenant', 'entity', 'role');
}

function makeContact(array $setup, array $overrides = []): Contact
{
    // Compute the next number manually — the model's creating hook uses
    // static::query()->max('number') which goes through BelongsToTenant scope
    // and returns null (→ 1) for every contact when there is no active session.
    $nextNumber = DB::table('contacts')
        ->where('tenant_id', $setup['tenant']->id)
        ->max('number') + 1;

    return Contact::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'entity_id' => $setup['entity']->id,
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName(),
        'role_id' => $setup['role']->id,
        'email' => fake()->unique()->safeEmail(),
        'status' => 'active',
        'number' => $nextNumber,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

test('unauthenticated user is redirected from contacts index', function (): void {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('user can view contacts index', function (): void {
    $setup = contactSetup();
    makeContact($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('contacts/Index')->has('contacts'));
});

test('contacts index filters by name', function (): void {
    $setup = contactSetup();
    makeContact($setup, ['first_name' => 'Álvaro', 'last_name' => 'Mendes']);
    makeContact($setup, ['first_name' => 'Sofia', 'last_name' => 'Lopes']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.index', ['name' => 'Álvaro']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.name', 'Álvaro'));
});

test('contacts index filters by email', function (): void {
    $setup = contactSetup();
    makeContact($setup, ['email' => 'unique@test.pt']);
    makeContact($setup, ['email' => 'other@test.pt']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.index', ['email' => 'unique@test']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.email', 'unique@test'));
});

// ---------------------------------------------------------------------------
// Create / Store
// ---------------------------------------------------------------------------

test('user can view contact creation form', function (): void {
    $setup = contactSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('contacts/Create'));
});

test('user can create a contact', function (): void {
    $setup = contactSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.store'), [
            'entity_id' => $setup['entity']->id,
            'first_name' => 'João',
            'last_name' => 'Ferreira',
            'role_id' => $setup['role']->id,
            'email' => 'joao.ferreira@empresa.pt',
            'gdpr_consent' => true,
            'status' => 'active',
        ])
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseHas('contacts', [
        'tenant_id' => $setup['tenant']->id,
        'first_name' => 'João',
        'last_name' => 'Ferreira',
    ]);
});

test('contact store validates required fields', function (): void {
    $setup = contactSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.store'), [])
        ->assertSessionHasErrors(['first_name']);
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('user can view a contact', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.show', $contact))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('contacts/Show')
            ->where('contact.id', $contact->id)
        );
});

test('show exposes duplicate candidates with same email', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup, ['email' => 'duplicado@empresa.pt']);
    makeContact($setup, ['email' => 'duplicado@empresa.pt']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.show', $contact))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('duplicate_candidates', 1));
});

test('show returns no duplicates when contact has no email or mobile', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup, ['email' => null, 'mobile' => null]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.show', $contact))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('duplicate_candidates', 0));
});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

test('user can view contact edit form', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('people.edit', $contact))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('contacts/Edit'));
});

test('user can update a contact', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup, ['first_name' => 'Antigo']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->put(route('people.update', $contact), [
            'entity_id' => $setup['entity']->id,
            'first_name' => 'Atualizado',
            'last_name' => 'Silva',
            'role_id' => $setup['role']->id,
            'gdpr_consent' => false,
            'status' => 'active',
        ])
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'first_name' => 'Atualizado']);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('user can delete a contact', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->delete(route('people.destroy', $contact))
        ->assertRedirect(route('people.index'));

    $this->assertSoftDeleted('contacts', ['id' => $contact->id]);
});

// ---------------------------------------------------------------------------
// Merge
// ---------------------------------------------------------------------------

test('user can merge a duplicate contact into primary', function (): void {
    $setup = contactSetup();

    $primary = makeContact($setup, [
        'first_name' => 'Maria',
        'email' => 'maria@empresa.pt',
        'notes' => 'Nota primária.',
    ]);
    $duplicate = makeContact($setup, [
        'first_name' => 'Maria',
        'email' => 'maria@empresa.pt',
        'notes' => 'Nota duplicada.',
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.merge', $primary), [
            'duplicate_contact_id' => $duplicate->id,
        ])
        ->assertRedirect(route('people.show', $primary));

    $this->assertSoftDeleted('contacts', ['id' => $duplicate->id]);

    $merged = $primary->fresh();
    expect($merged->notes)->toContain('Nota primária.')->toContain('Nota duplicada.');
});

test('merging a contact with itself is rejected by the form request', function (): void {
    $setup = contactSetup();
    $contact = makeContact($setup);

    // ContactMergeRequest::authorize() returns false when duplicate_contact_id === contact id
    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.merge', $contact), [
            'duplicate_contact_id' => $contact->id,
        ])
        ->assertForbidden();
});

test('merge promotes active status from duplicate to primary', function (): void {
    $setup = contactSetup();

    $primary = makeContact($setup, ['status' => 'inactive']);
    $duplicate = makeContact($setup, ['status' => 'active']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.merge', $primary), [
            'duplicate_contact_id' => $duplicate->id,
        ]);

    expect($primary->fresh()->status)->toBe('active');
});

test('merge preserves primary data when primary fields are non-empty', function (): void {
    $setup = contactSetup();

    $primary = makeContact($setup, [
        'first_name' => 'Carlos',
        'phone' => '210000000',
    ]);
    $duplicate = makeContact($setup, [
        'first_name' => 'Carlos Alt',
        'phone' => '210000001',
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('people.merge', $primary), [
            'duplicate_contact_id' => $duplicate->id,
        ]);

    $merged = $primary->fresh();
    expect($merged->first_name)->toBe('Carlos');
    expect($merged->phone)->toBe('210000000');
});
