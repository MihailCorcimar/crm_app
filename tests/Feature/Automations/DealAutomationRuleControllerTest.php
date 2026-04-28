<?php

use App\Models\DealAutomationRule;
use App\Models\Tenant;
use App\Models\User;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function automationSetup(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create(['owner_user_id' => $user->id]);
    $tenant->members()->attach($user->id, ['role' => 'owner', 'can_create_tenants' => true]);
    $user->forceFill(['current_tenant_id' => $tenant->id])->save();

    return compact('user', 'tenant');
}

function makeRule(array $setup, array $overrides = []): DealAutomationRule
{
    return DealAutomationRule::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Regra Teste',
        'trigger_type' => 'deal_inactivity',
        'inactivity_days' => 7,
        'action_type' => 'create_calendar_activity',
        'activity_type' => 'task',
        'activity_due_in_days' => 0,
        'activity_priority' => 'medium',
        'activity_title_template' => 'Follow up - {deal_title}',
        'notify_internal' => true,
        'status' => DealAutomationRule::STATUS_ACTIVE,
        'created_by' => $setup['user']->id,
        'updated_by' => $setup['user']->id,
    ], $overrides));
}

function rulePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Nova Regra',
        'inactivity_days' => 5,
        'activity_type' => 'call',
        'activity_due_in_days' => 1,
        'activity_priority' => 'high',
        'activity_title_template' => 'Contactar {entity_name}',
        'activity_description_template' => 'Negócio inativo há {days_without_activity} dias.',
        'notify_internal' => true,
        'notification_message' => 'Atividade criada para {deal_title}.',
        'status' => DealAutomationRule::STATUS_ACTIVE,
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

test('unauthenticated user is redirected from automations index', function (): void {
    $this->get(route('automations.deal-rules.index'))->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

test('user can view automations index', function (): void {
    $setup = automationSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('automations.deal-rules.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations/deal-rules/Index')
            ->has('rules')
        );
});

// ---------------------------------------------------------------------------
// Create / Store
// ---------------------------------------------------------------------------

test('user can view rule creation form', function (): void {
    $setup = automationSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('automations.deal-rules.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('automations/deal-rules/Create')
            ->has('defaults')
        );
});

test('user can create an automation rule', function (): void {
    $setup = automationSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('automations.deal-rules.store'), rulePayload())
        ->assertRedirect(route('automations.deal-rules.index'));

    $this->assertDatabaseHas('deal_automation_rules', [
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Nova Regra',
        'inactivity_days' => 5,
        'trigger_type' => 'deal_inactivity',
    ]);
});

test('rule store validates required fields', function (): void {
    $setup = automationSetup();

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->post(route('automations.deal-rules.store'), [])
        ->assertSessionHasErrors(['name', 'inactivity_days']);
});

// ---------------------------------------------------------------------------
// Edit / Update
// ---------------------------------------------------------------------------

test('user can view rule edit form', function (): void {
    $setup = automationSetup();
    $rule = makeRule($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->get(route('automations.deal-rules.edit', $rule))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('automations/deal-rules/Edit'));
});

test('user can update an automation rule', function (): void {
    $setup = automationSetup();
    $rule = makeRule($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->put(route('automations.deal-rules.update', $rule), rulePayload([
            'name' => 'Regra Atualizada',
            'inactivity_days' => 14,
        ]))
        ->assertRedirect(route('automations.deal-rules.index'));

    $this->assertDatabaseHas('deal_automation_rules', [
        'id' => $rule->id,
        'name' => 'Regra Atualizada',
        'inactivity_days' => 14,
    ]);
});

// ---------------------------------------------------------------------------
// Destroy
// ---------------------------------------------------------------------------

test('user can delete an automation rule', function (): void {
    $setup = automationSetup();
    $rule = makeRule($setup);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->delete(route('automations.deal-rules.destroy', $rule))
        ->assertRedirect(route('automations.deal-rules.index'));

    $this->assertDatabaseMissing('deal_automation_rules', ['id' => $rule->id]);
});

// ---------------------------------------------------------------------------
// Toggle Status
// ---------------------------------------------------------------------------

test('user can toggle rule from active to paused', function (): void {
    $setup = automationSetup();
    $rule = makeRule($setup, ['status' => DealAutomationRule::STATUS_ACTIVE]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patch(route('automations.deal-rules.toggle-status', $rule))
        ->assertRedirect();

    expect($rule->fresh()->status)->toBe(DealAutomationRule::STATUS_PAUSED);
});

test('user can toggle rule from paused to active', function (): void {
    $setup = automationSetup();
    $rule = makeRule($setup, ['status' => DealAutomationRule::STATUS_PAUSED]);

    $this->actingAs($setup['user'])
        ->withSession(['current_tenant_id' => $setup['tenant']->id])
        ->patch(route('automations.deal-rules.toggle-status', $rule))
        ->assertRedirect();

    expect($rule->fresh()->status)->toBe(DealAutomationRule::STATUS_ACTIVE);
});
