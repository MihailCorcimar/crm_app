<?php

use App\Models\AutomationNotification;
use App\Models\CalendarEvent;
use App\Models\Country;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\DealAutomationRuleExecution;
use App\Models\Entity;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DealAutomationEngineService;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function engineSetup(): array
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
        'name' => 'Empresa Engine Teste',
        'country_id' => $country->id,
        'status' => 'active',
    ]);

    return compact('user', 'tenant', 'entity');
}

function makeInactiveDeal(array $setup, int $daysAgo = 10, array $overrides = []): Deal
{
    $deal = Deal::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'entity_id' => $setup['entity']->id,
        'title' => 'Deal Inativo',
        'stage' => Deal::STAGE_LEAD,
        'value' => 500.00,
        'probability' => 30,
        'owner_id' => $setup['user']->id,
    ], $overrides));

    // Eloquent's updateTimestamps() may override the values passed to create(),
    // so force them to the past with a raw update after the record exists.
    $past = now()->subDays($daysAgo)->toDateTimeString();
    DB::table('deals')->where('id', $deal->id)->update([
        'created_at' => $past,
        'updated_at' => $past,
    ]);

    return $deal->fresh();
}

function makeActiveRule(array $setup, array $overrides = []): DealAutomationRule
{
    return DealAutomationRule::withoutGlobalScopes()->create(array_merge([
        'tenant_id' => $setup['tenant']->id,
        'name' => 'Regra Engine',
        'trigger_type' => 'deal_inactivity',
        'inactivity_days' => 7,
        'action_type' => 'create_calendar_activity',
        'activity_type' => 'task',
        'activity_due_in_days' => 0,
        'activity_priority' => 'medium',
        'activity_title_template' => 'Follow up - {deal_title}',
        'activity_description_template' => 'Inativo há {days_without_activity} dias.',
        'notify_internal' => true,
        'notification_message' => 'Atividade criada para {deal_title}.',
        'status' => DealAutomationRule::STATUS_ACTIVE,
        'created_by' => $setup['user']->id,
        'updated_by' => $setup['user']->id,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('returns zero counts when no active rules exist', function (): void {
    $setup = engineSetup();

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary)->toBe([
        'rules_processed' => 0,
        'deals_scanned' => 0,
        'activities_created' => 0,
        'notifications_created' => 0,
    ]);
});

test('paused rule is not processed', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup, ['status' => DealAutomationRule::STATUS_PAUSED]);
    makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary['rules_processed'])->toBe(0);
    expect($summary['activities_created'])->toBe(0);
});

test('creates activity and notification for inactive deal', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup);
    makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary['rules_processed'])->toBe(1);
    expect($summary['activities_created'])->toBe(1);
    expect($summary['notifications_created'])->toBe(1);

    $this->assertDatabaseHas('calendar_events', [
        'tenant_id' => $setup['tenant']->id,
        'owner_id' => $setup['user']->id,
    ]);

    $this->assertDatabaseHas('automation_notifications', [
        'tenant_id' => $setup['tenant']->id,
        'user_id' => $setup['user']->id,
    ]);
});

test('skips deal that has recent activity within inactivity window', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup, ['inactivity_days' => 7]);
    makeInactiveDeal($setup, 3);

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary['activities_created'])->toBe(0);
});


test('skips deal already triggered for the same anchor timestamp', function (): void {
    $setup = engineSetup();
    $rule = makeActiveRule($setup);
    $deal = makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);

    $summary1 = $engine->run($setup['tenant']->id);
    expect($summary1['activities_created'])->toBe(1);

    $summary2 = $engine->run($setup['tenant']->id);
    expect($summary2['activities_created'])->toBe(0);

    expect(CalendarEvent::withoutGlobalScopes()
        ->where('eventable_type', Deal::class)
        ->where('eventable_id', $deal->id)
        ->count()
    )->toBe(1);
});

test('skips deals in won or lost stage', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup);
    makeInactiveDeal($setup, 10, ['stage' => Deal::STAGE_WON]);
    makeInactiveDeal($setup, 10, ['stage' => Deal::STAGE_LOST]);

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary['activities_created'])->toBe(0);
});

test('no notification created when notify_internal is false', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup, ['notify_internal' => false]);
    makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);
    $summary = $engine->run($setup['tenant']->id);

    expect($summary['activities_created'])->toBe(1);
    expect($summary['notifications_created'])->toBe(0);
    expect(AutomationNotification::withoutGlobalScopes()->count())->toBe(0);
});

test('template placeholders are resolved in activity title', function (): void {
    $setup = engineSetup();

    // Log in the user so TenantContext can resolve tenant_id for entity lazy-loading
    auth()->login($setup['user']);

    makeActiveRule($setup, [
        'activity_title_template' => 'Follow up - {deal_title} ({entity_name})',
    ]);

    $deal = makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);
    $engine->run($setup['tenant']->id);

    $event = CalendarEvent::withoutGlobalScopes()
        ->where('eventable_type', Deal::class)
        ->where('eventable_id', $deal->id)
        ->first();

    expect($event)->not->toBeNull();
    expect($event->title)
        ->toContain($deal->title)
        ->toContain('Empresa Engine Teste')
        ->not->toContain('{entity_name}')
        ->not->toContain('{deal_title}');
});

test('activity type determines event duration', function (): void {
    // Each type needs an isolated tenant so shared entity_id does not cause
    // cross-deal contamination in latestActivityAt (which checks entity events).
    foreach (['meeting' => 45, 'call' => 20, 'note' => 10, 'task' => 30] as $type => $expectedMinutes) {
        $setup = engineSetup();
        makeActiveRule($setup, ['activity_type' => $type]);
        makeInactiveDeal($setup, 10);

        $engine = app(DealAutomationEngineService::class);
        $engine->run($setup['tenant']->id);

        $duration = CalendarEvent::withoutGlobalScopes()
            ->where('tenant_id', $setup['tenant']->id)
            ->value('duration_minutes');

        expect($duration)->toBe($expectedMinutes, "Expected {$expectedMinutes} min for type '{$type}'");
    }
});

test('execution log is created for each processed deal', function (): void {
    $setup = engineSetup();

    makeActiveRule($setup);
    $deal = makeInactiveDeal($setup, 10);

    $engine = app(DealAutomationEngineService::class);
    $engine->run($setup['tenant']->id);

    $this->assertDatabaseHas('deal_automation_rule_executions', [
        'tenant_id' => $setup['tenant']->id,
        'deal_id' => $deal->id,
        'status' => 'created',
    ]);
});

test('run scoped to tenant does not affect other tenants', function (): void {
    $setupA = engineSetup();
    $setupB = engineSetup();

    makeActiveRule($setupA);
    makeInactiveDeal($setupA, 10);

    makeActiveRule($setupB);
    makeInactiveDeal($setupB, 10);

    $engine = app(DealAutomationEngineService::class);
    $summaryA = $engine->run($setupA['tenant']->id);

    expect($summaryA['activities_created'])->toBe(1);

    $bEvents = CalendarEvent::withoutGlobalScopes()
        ->where('tenant_id', $setupB['tenant']->id)
        ->count();

    expect($bEvents)->toBe(0);
});
