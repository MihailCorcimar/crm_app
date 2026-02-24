<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanChangeLog;
use App\Models\Tenant;
use App\Support\TenantContext;
use App\Support\TenantSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TenantBillingController extends Controller
{
    public function __construct(
        private TenantSubscriptionService $subscriptionService
    ) {}

    public function show(Request $request): Response
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('view', $tenant);

        $dashboard = $this->subscriptionService->dashboard($tenant, $request->user());
        $audit = $this->auditPayload($request, $tenant);

        return Inertia::render('tenants/Billing', [
            'tenantDetails' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            ...$dashboard,
            'audit' => $audit,
            'canManageBilling' => $request->user()?->can('manageBilling', $tenant) ?? false,
        ]);
    }

    public function changePlan(Request $request, Plan $plan): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('manageBilling', $tenant);

        $this->subscriptionService->changePlan($tenant, $plan, $request->user());

        return to_route('tenants.billing.show');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('manageBilling', $tenant);

        $this->subscriptionService->cancelAtPeriodEnd($tenant, $request->user());

        return to_route('tenants.billing.show');
    }

    public function resume(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('manageBilling', $tenant);

        $this->subscriptionService->resume($tenant, $request->user());

        return to_route('tenants.billing.show');
    }

    private function activeTenant(Request $request): Tenant
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $tenant = $request->user()
            ?->tenants()
            ->where('tenants.id', $tenantId)
            ->first();

        abort_if($tenant === null, 403, 'You are not authorized for the active tenant.');

        return $tenant;
    }

    /**
     * @return array{
     *   view: string,
     *   filters: array{audit_type: string, audit_actor: string, audit_per_page: int},
     *   filter_options: array{types: array<int, string>, actors: array<int, array{id: int, name: string}>},
     *   summary_logs: array<int, array{
     *      key: string,
     *      change_type: string,
     *      occurrences: int,
     *      first_effective_at: string|null,
     *      last_effective_at: string|null,
     *      last_log: array{
     *          id: int,
     *          change_type: string,
     *          effective_at: string|null,
     *          proration_amount_cents: int,
     *          actor: array{id: int, name: string, email: string}|null,
     *          from_plan: array{id: int, code: string, name: string}|null,
     *          to_plan: array{id: int, code: string, name: string}|null,
     *          metadata: array<string, mixed>
     *      }
     *   }>,
     *   raw_logs: array{
     *      data: array<int, array{
     *          id: int,
     *          change_type: string,
     *          effective_at: string|null,
     *          proration_amount_cents: int,
     *          actor: array{id: int, name: string, email: string}|null,
     *          from_plan: array{id: int, code: string, name: string}|null,
     *          to_plan: array{id: int, code: string, name: string}|null,
     *          metadata: array<string, mixed>
     *      }>,
     *      current_page: int,
     *      last_page: int,
     *      per_page: int,
     *      total: int,
     *      from: int|null,
     *      to: int|null
     *   }
     * }
     */
    private function auditPayload(Request $request, Tenant $tenant): array
    {
        $view = (string) $request->query('audit_view', 'summary');
        if (! in_array($view, ['summary', 'raw'], true)) {
            $view = 'summary';
        }

        $typeFilter = trim((string) $request->query('audit_type', 'all'));
        $actorFilter = trim((string) $request->query('audit_actor', 'all'));

        $perPage = (int) $request->query('audit_per_page', 10);
        $perPage = max(5, min(50, $perPage));

        $rawBaseQuery = PlanChangeLog::query()
            ->where('tenant_id', $tenant->id)
            ->with(['user:id,name,email', 'fromPlan:id,code,name', 'toPlan:id,code,name'])
            ->orderByDesc('effective_at')
            ->orderByDesc('id');

        if ($typeFilter !== '' && $typeFilter !== 'all') {
            $rawBaseQuery->where('change_type', $typeFilter);
        }

        if ($actorFilter !== '' && $actorFilter !== 'all' && is_numeric($actorFilter)) {
            $rawBaseQuery->where('user_id', (int) $actorFilter);
        }

        $rawPaginator = (clone $rawBaseQuery)
            ->paginate($perPage, ['*'], 'audit_page')
            ->withQueryString();

        $summarySource = (clone $rawBaseQuery)
            ->limit(500)
            ->get();

        $summaryLogs = $summarySource
            ->groupBy(function (PlanChangeLog $log): string {
                return implode('|', [
                    (string) $log->change_type,
                    (string) ($log->user_id ?? 'null'),
                    (string) ($log->from_plan_id ?? 'null'),
                    (string) ($log->to_plan_id ?? 'null'),
                ]);
            })
            ->map(function (Collection $group): array {
                $sorted = $group->sortByDesc(function (PlanChangeLog $log): int {
                    return $log->effective_at?->timestamp ?? 0;
                })->values();

                /** @var PlanChangeLog $latest */
                $latest = $sorted->first();
                /** @var PlanChangeLog $oldest */
                $oldest = $sorted->last();

                return [
                    'key' => (string) $latest->change_type.'|'.(string) $latest->user_id.'|'.(string) $latest->from_plan_id.'|'.(string) $latest->to_plan_id,
                    'change_type' => (string) $latest->change_type,
                    'occurrences' => $group->count(),
                    'first_effective_at' => $oldest->effective_at?->toIso8601String(),
                    'last_effective_at' => $latest->effective_at?->toIso8601String(),
                    'last_log' => $this->auditLogPayload($latest),
                ];
            })
            ->sortByDesc(fn (array $item): string => (string) ($item['last_effective_at'] ?? ''))
            ->values()
            ->take(20)
            ->all();

        $typeOptions = PlanChangeLog::query()
            ->where('tenant_id', $tenant->id)
            ->select('change_type')
            ->distinct()
            ->orderBy('change_type')
            ->pluck('change_type')
            ->map(fn ($type): string => (string) $type)
            ->values()
            ->all();

        $actorOptions = PlanChangeLog::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('user_id')
            ->join('users', 'users.id', '=', 'plan_change_logs.user_id')
            ->select('users.id', 'users.name')
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();

        return [
            'view' => $view,
            'filters' => [
                'audit_type' => $typeFilter !== '' ? $typeFilter : 'all',
                'audit_actor' => $actorFilter !== '' ? $actorFilter : 'all',
                'audit_per_page' => $perPage,
            ],
            'filter_options' => [
                'types' => $typeOptions,
                'actors' => $actorOptions,
            ],
            'summary_logs' => $summaryLogs,
            'raw_logs' => [
                'data' => collect($rawPaginator->items())
                    ->map(fn (PlanChangeLog $log): array => $this->auditLogPayload($log))
                    ->values()
                    ->all(),
                'current_page' => $rawPaginator->currentPage(),
                'last_page' => $rawPaginator->lastPage(),
                'per_page' => $rawPaginator->perPage(),
                'total' => $rawPaginator->total(),
                'from' => $rawPaginator->firstItem(),
                'to' => $rawPaginator->lastItem(),
            ],
        ];
    }

    /**
     * @return array{
     *   id: int,
     *   change_type: string,
     *   effective_at: string|null,
     *   proration_amount_cents: int,
     *   actor: array{id: int, name: string, email: string}|null,
     *   from_plan: array{id: int, code: string, name: string}|null,
     *   to_plan: array{id: int, code: string, name: string}|null,
     *   metadata: array<string, mixed>
     * }
     */
    private function auditLogPayload(PlanChangeLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'change_type' => (string) $log->change_type,
            'effective_at' => $log->effective_at?->toIso8601String(),
            'proration_amount_cents' => (int) $log->proration_amount_cents,
            'actor' => $log->user?->only(['id', 'name', 'email']),
            'from_plan' => $log->fromPlan?->only(['id', 'code', 'name']),
            'to_plan' => $log->toPlan?->only(['id', 'code', 'name']),
            'metadata' => $log->metadata ?? [],
        ];
    }
}
