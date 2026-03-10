<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealAutomationRuleRequest;
use App\Models\AutomationNotification;
use App\Models\DealAutomationRule;
use App\Models\DealAutomationRuleExecution;
use App\Support\DealAutomationEngineService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DealAutomationRuleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(DealAutomationRule::class, 'rule');
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DealAutomationRule::class);

        $rules = DealAutomationRule::query()
            ->orderByDesc('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (DealAutomationRule $rule): array => [
                'id' => $rule->id,
                'name' => $rule->name,
                'inactivity_days' => (int) $rule->inactivity_days,
                'activity_type' => $rule->activity_type,
                'activity_due_in_days' => (int) $rule->activity_due_in_days,
                'activity_priority' => $rule->activity_priority,
                'notify_internal' => (bool) $rule->notify_internal,
                'status' => $rule->status,
                'updated_at' => $rule->updated_at?->format('d/m/Y H:i'),
            ]);

        $tenantId = TenantContext::id($request) ?? 0;
        $userId = (int) $request->user()->getAuthIdentifier();

        $notifications = AutomationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (AutomationNotification $notification): array => [
                'id' => $notification->id,
                'title' => $notification->title,
                'message' => $notification->message,
                'deal_id' => $notification->deal_id,
                'calendar_event_id' => $notification->calendar_event_id,
                'read_at' => $notification->read_at?->format('d/m/Y H:i'),
                'created_at' => $notification->created_at?->format('d/m/Y H:i'),
            ])
            ->all();

        $lastRuns = DealAutomationRuleExecution::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['rule:id,name', 'deal:id,title'])
            ->orderByDesc('triggered_at')
            ->limit(20)
            ->get()
            ->map(fn (DealAutomationRuleExecution $run): array => [
                'id' => $run->id,
                'rule_name' => $run->rule?->name ?? '-',
                'deal_id' => $run->deal_id,
                'deal_title' => $run->deal?->title ?? '-',
                'status' => $run->status,
                'status_reason' => $run->status_reason,
                'triggered_at' => $run->triggered_at?->format('d/m/Y H:i'),
            ])
            ->all();

        return Inertia::render('automations/deal-rules/Index', [
            'rules' => $rules,
            'notifications' => $notifications,
            'runs' => $lastRuns,
            'unreadNotificationsCount' => collect($notifications)->whereNull('read_at')->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('automations/deal-rules/Create', [
            'defaults' => $this->defaults(),
        ]);
    }

    public function store(DealAutomationRuleRequest $request): RedirectResponse
    {
        $tenantId = TenantContext::id($request) ?? 0;

        DealAutomationRule::withoutGlobalScopes()->create(
            $this->payload($request->validated()) + [
                'tenant_id' => $tenantId,
                'trigger_type' => 'deal_inactivity',
                'action_type' => 'create_calendar_activity',
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]
        );

        return to_route('automations.deal-rules.index');
    }

    public function edit(DealAutomationRule $rule): Response
    {
        return Inertia::render('automations/deal-rules/Edit', [
            'rule' => [
                'id' => $rule->id,
                'name' => $rule->name,
                'inactivity_days' => (int) $rule->inactivity_days,
                'activity_type' => $rule->activity_type,
                'activity_due_in_days' => (int) $rule->activity_due_in_days,
                'activity_priority' => $rule->activity_priority,
                'activity_title_template' => $rule->activity_title_template,
                'activity_description_template' => $rule->activity_description_template,
                'notify_internal' => (bool) $rule->notify_internal,
                'notification_message' => $rule->notification_message,
                'status' => $rule->status,
            ],
        ]);
    }

    public function update(DealAutomationRuleRequest $request, DealAutomationRule $rule): RedirectResponse
    {
        $rule->update(
            $this->payload($request->validated()) + [
                'updated_by' => $request->user()?->id,
            ]
        );

        return to_route('automations.deal-rules.index');
    }

    public function destroy(DealAutomationRule $rule): RedirectResponse
    {
        $rule->delete();

        return to_route('automations.deal-rules.index');
    }

    public function toggleStatus(DealAutomationRule $rule): RedirectResponse
    {
        $this->authorize('update', $rule);

        $rule->update([
            'status' => $rule->status === DealAutomationRule::STATUS_ACTIVE
                ? DealAutomationRule::STATUS_PAUSED
                : DealAutomationRule::STATUS_ACTIVE,
            'updated_by' => auth()->id(),
        ]);

        return back();
    }

    public function runNow(Request $request, DealAutomationEngineService $engineService): RedirectResponse
    {
        $this->authorize('viewAny', DealAutomationRule::class);
        $tenantId = TenantContext::id($request) ?? 0;

        $summary = $engineService->run($tenantId, 500);

        return back()->with('success', sprintf(
            'Automacoes executadas. Regras: %d | Atividades: %d | Notificacoes: %d',
            $summary['rules_processed'],
            $summary['activities_created'],
            $summary['notifications_created']
        ));
    }

    public function markNotificationRead(AutomationNotification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        $notification->update([
            'read_at' => now(),
        ]);

        return back();
    }

    public function markAllNotificationsRead(Request $request): RedirectResponse
    {
        $tenantId = TenantContext::id($request) ?? 0;
        $userId = (int) $request->user()->getAuthIdentifier();

        AutomationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    /**
     * @return array{name: string, inactivity_days: int, activity_type: string, activity_due_in_days: int, activity_priority: string, activity_title_template: string, activity_description_template: string, notify_internal: bool, notification_message: string, status: string}
     */
    private function defaults(): array
    {
        return [
            'name' => 'Regra de inatividade',
            'inactivity_days' => 7,
            'activity_type' => 'task',
            'activity_due_in_days' => 0,
            'activity_priority' => 'medium',
            'activity_title_template' => 'Follow up automatico - {deal_title}',
            'activity_description_template' => 'Negocio sem atividade ha {days_without_activity} dias. Retomar contacto com {entity_name}.',
            'notify_internal' => true,
            'notification_message' => 'Foi criada uma nova atividade automatica para {deal_title}.',
            'status' => DealAutomationRule::STATUS_ACTIVE,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'name' => trim((string) $validated['name']),
            'inactivity_days' => (int) $validated['inactivity_days'],
            'activity_type' => (string) $validated['activity_type'],
            'activity_due_in_days' => (int) $validated['activity_due_in_days'],
            'activity_priority' => (string) $validated['activity_priority'],
            'activity_title_template' => trim((string) $validated['activity_title_template']),
            'activity_description_template' => isset($validated['activity_description_template'])
                ? trim((string) $validated['activity_description_template'])
                : null,
            'notify_internal' => (bool) $validated['notify_internal'],
            'notification_message' => isset($validated['notification_message'])
                ? trim((string) $validated['notification_message'])
                : null,
            'status' => (string) $validated['status'],
        ];
    }
}
