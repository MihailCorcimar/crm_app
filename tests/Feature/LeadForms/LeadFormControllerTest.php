<?php

use App\Models\LeadForm;
use App\Models\LeadFormSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LeadFormFieldCatalog;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function leadFormSetup(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $user->id]);
    $tenant->members()->attach($user->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $user->forceFill(['current_tenant_id' => $tenant->id])->save();

    return compact('user', 'tenant');
}

function makeLeadForm(array $setup, array $overrides = []): LeadForm
{
    return LeadForm::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Formulário Teste',
        'slug' => 'formulario-teste-' . fake()->unique()->numerify('####'),
        'status' => LeadForm::STATUS_ACTIVE,
        'requires_captcha' => false,
        'confirmation_message' => 'Obrigado pelo contacto.',
        'field_schema' => app(LeadFormFieldCatalog::class)->defaults(),
        'conversion_settings' => [
            'create_deal' => false,
            'deal_stage' => 'lead',
            'deal_probability' => 20,
        ],
        'embed_token' => Str::random(48),
        'created_by' => $setup['user']->id,
        'updated_by' => $setup['user']->id,
    ], $overrides));
}

function leadFormPayload(array $overrides = []): array
{
    // field_schema must have at least one enabled field and follow the expected structure
    $schema = app(LeadFormFieldCatalog::class)->defaults();

    return array_merge([
        'name' => 'Novo Formulário',
        'slug' => 'novo-formulario-' . fake()->unique()->numerify('####'),
        'status' => LeadForm::STATUS_ACTIVE,
        'requires_captcha' => false,
        'confirmation_message' => 'Obrigado.',
        'field_schema' => $schema,
        'conversion_settings' => [
            'create_deal' => false,
            'entity_name_field_key' => null,
            'deal_title_field_key' => null,
            'deal_title_template' => 'Lead inbound',
            'deal_value_field_key' => null,
            'deal_stage' => 'lead',
            'deal_owner_id' => null,
            'deal_probability' => 20,
        ],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

test('unauthenticated user is redirected from lead forms index', function (): void {
    $this->get(route('lead-forms.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('user can view lead forms index', function (): void {
    $setup = leadFormSetup();
    makeLeadForm($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lead-forms/Index')->has('forms'));
});

test('lead forms index filters by query', function (): void {
    $setup = leadFormSetup();
    makeLeadForm($setup, ['name' => 'Formulário Especial']);
    makeLeadForm($setup, ['name' => 'Outro Formulário']);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.index', ['q' => 'Especial']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.q', 'Especial'));
});

test('lead forms index filters by status', function (): void {
    $setup = leadFormSetup();
    makeLeadForm($setup, ['status' => LeadForm::STATUS_ACTIVE]);
    makeLeadForm($setup, ['status' => LeadForm::STATUS_INACTIVE]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.index', ['status' => LeadForm::STATUS_INACTIVE]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.status', LeadForm::STATUS_INACTIVE));
});

// ---------------------------------------------------------------------------
// Create / Store
// ---------------------------------------------------------------------------

test('user can view lead form creation page', function (): void {
    $setup = leadFormSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lead-forms/Create'));
});

test('user can create a lead form', function (): void {
    $setup = leadFormSetup();
    $payload = leadFormPayload();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('lead-forms.store'), $payload)
        ->assertRedirect(route('lead-forms.index'));

    $this->assertDatabaseHas('lead_forms', [
        'tenant_id' => $setup['tenant']->id,
        'name' => $payload['name'],
    ]);
});

test('created lead form gets a random embed token', function (): void {
    $setup = leadFormSetup();
    $payload = leadFormPayload();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('lead-forms.store'), $payload);

    $form = LeadForm::withoutGlobalScopes()
        ->where('tenant_id', $setup['tenant']->id)
        ->where('name', $payload['name'])
        ->first();

    expect($form)->not->toBeNull();
    expect(strlen((string) $form->embed_token))->toBeGreaterThanOrEqual(48);
});

test('lead form store validates required fields', function (): void {
    $setup = leadFormSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('lead-forms.store'), [])
        ->assertSessionHasErrors(['name', 'status']);
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

test('user can view a lead form with submissions', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);

    LeadFormSubmission::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'lead_form_id' => $form->id,
        'status' => 'pending',
        'submitted_at' => now(),
        'payload' => ['name' => 'Teste'],
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.show', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('lead-forms/Show')
            ->where('leadForm.id', $form->id)
            ->has('submissions.data', 1)
        );
});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

test('user can view lead form edit page', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('lead-forms.edit', $form))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('lead-forms/Edit'));
});

test('user can update a lead form', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->put(route('lead-forms.update', $form), leadFormPayload([
            'name' => 'Formulário Atualizado',
            'status' => LeadForm::STATUS_INACTIVE,
        ]))
        ->assertRedirect(route('lead-forms.show', $form));

    $this->assertDatabaseHas('lead_forms', [
        'id' => $form->id,
        'name' => 'Formulário Atualizado',
        'status' => LeadForm::STATUS_INACTIVE,
    ]);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('user can delete a lead form', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->delete(route('lead-forms.destroy', $form))
        ->assertRedirect(route('lead-forms.index'));

    $this->assertDatabaseMissing('lead_forms', ['id' => $form->id]);
});

// ---------------------------------------------------------------------------
// Submissions: ignore  (route is PATCH)
// ---------------------------------------------------------------------------

test('user can ignore a lead form submission', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);

    $submission = LeadFormSubmission::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'lead_form_id' => $form->id,
        'status' => 'pending',
        'submitted_at' => now(),
        'payload' => [],
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patch(route('lead-forms.submissions.ignore', [$form, $submission]))
        ->assertRedirect();

    $this->assertDatabaseHas('lead_form_submissions', [
        'id' => $submission->id,
        'status' => 'ignored',
    ]);
});

test('ignoring a submission from another form returns 404', function (): void {
    $setup = leadFormSetup();
    $form = makeLeadForm($setup);
    $otherForm = makeLeadForm($setup);

    $submission = LeadFormSubmission::withoutGlobalScopes()->create([
        'tenant_id' => $setup['tenant']->id,
        'lead_form_id' => $otherForm->id,
        'status' => 'pending',
        'submitted_at' => now(),
        'payload' => [],
    ]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patch(route('lead-forms.submissions.ignore', [$form, $submission]))
        ->assertNotFound();
});
