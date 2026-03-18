<?php

namespace App\Http\Controllers;

use App\Models\AutomationNotification;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\DealAutomationRuleExecution;
use App\Models\DealEmailLog;
use App\Models\DealProduct;
use App\Models\LeadFormSubmission;
use App\Support\DealStageService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DealStageService $dealStageService
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $tenantId = TenantContext::id($request) ?? (int) ($request->user()?->current_tenant_id ?? 0);
        $userId = (int) ($request->user()?->id ?? 0);
        $now = now();

        if ($tenantId <= 0) {
            return Inertia::render('Dashboard', [
                'summary' => [
                    'deals_active_count' => 0,
                    'pipeline_total' => 0,
                    'follow_ups_due_today' => 0,
                    'proposals_sent_week' => 0,
                ],
                'pipeline' => [],
                'agenda' => [
                    'upcoming' => [],
                    'overdue' => [],
                ],
                'automations' => [
                    'stalled_deals_count' => 0,
                    'recent_executions_count' => 0,
                    'unread_notifications_count' => 0,
                    'recent_executions' => [],
                ],
                'leads' => [
                    'total_7d' => 0,
                    'converted_7d' => 0,
                    'ignored_7d' => 0,
                    'new_7d' => 0,
                    'total_30d' => 0,
                    'conversion_rate_7d' => 0,
                ],
                'top_products' => [],
            ]);
        }

        $openStages = [Deal::STAGE_LEAD, Deal::STAGE_PROPOSAL, Deal::STAGE_NEGOTIATION, Deal::STAGE_FOLLOW_UP];

        $dealsActiveCount = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('stage', $openStages)
            ->count();

        $pipelineTotal = (float) Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('stage', $openStages)
            ->sum('value');

        $followUpsDueToday = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('follow_up_active', true)
            ->whereNotNull('follow_up_next_send_at')
            ->whereDate('follow_up_next_send_at', $now->toDateString())
            ->count();

        $proposalsSentWeek = DealEmailLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('email_type', 'proposal')
            ->whereBetween('sent_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])
            ->count();

        $pipelineRows = Deal::withoutGlobalScopes()
            ->select('stage')
            ->selectRaw('COUNT(*) as deals_count')
            ->selectRaw('COALESCE(SUM(value), 0) as value_total')
            ->where('tenant_id', $tenantId)
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $pipeline = collect($this->dealStageService->forTenant($tenantId))
            ->map(function (array $stage) use ($pipelineRows): array {
                $row = $pipelineRows->get($stage['value']);

                return [
                    'stage' => (string) $stage['value'],
                    'label' => (string) $stage['label'],
                    'count' => $row !== null ? (int) ($row->deals_count ?? 0) : 0,
                    'value_total' => $row !== null ? (float) ($row->value_total ?? 0) : 0.0,
                ];
            })
            ->values()
            ->all();

        $agendaEvents = CalendarEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->where('status', 'active')
            ->with('eventable')
            ->orderBy('start_at')
            ->limit(250)
            ->get();

        $upcoming = [];
        $overdue = [];
        $endOfDay = CarbonImmutable::instance($now)->endOfDay();
        $nowImmutable = CarbonImmutable::instance($now);

        foreach ($agendaEvents as $event) {
            $startAt = $event->startAt();
            $endAt = $event->endAt();
            $row = [
                'id' => $event->id,
                'title' => $event->title ?: '(Sem titulo)',
                'start_at' => $startAt->format('d/m/Y H:i'),
                'end_at' => $endAt->format('d/m/Y H:i'),
                'location' => $event->location,
                'link' => route('calendar.edit', $event),
            ];

            if ($startAt->greaterThanOrEqualTo($nowImmutable) && $startAt->lessThanOrEqualTo($endOfDay)) {
                $upcoming[] = $row;
            } elseif ($endAt->lessThan($nowImmutable)) {
                $overdue[] = $row;
            }
        }

        $upcoming = array_slice($upcoming, 0, 6);
        $overdue = array_slice($overdue, 0, 6);

        $minRuleDays = DealAutomationRule::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', DealAutomationRule::STATUS_ACTIVE)
            ->min('inactivity_days');

        $stalledDealsCount = 0;
        if (is_numeric($minRuleDays) && (int) $minRuleDays > 0) {
            $stalledDealsCount = Deal::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereIn('stage', $openStages)
                ->where('updated_at', '<=', $now->copy()->subDays((int) $minRuleDays))
                ->count();
        }

        $recentExecutionsCount = DealAutomationRuleExecution::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('triggered_at', '>=', $now->copy()->subDays(7))
            ->count();

        $unreadNotificationsCount = AutomationNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $recentExecutions = DealAutomationRuleExecution::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->with(['rule:id,name', 'deal:id,title'])
            ->orderByDesc('triggered_at')
            ->limit(5)
            ->get()
            ->map(fn (DealAutomationRuleExecution $execution): array => [
                'id' => $execution->id,
                'rule_name' => $execution->rule?->name ?? '-',
                'deal_title' => $execution->deal?->title ?? '-',
                'status' => $execution->status,
                'triggered_at' => $execution->triggered_at?->format('d/m/Y H:i'),
            ])
            ->all();

        $from7d = $now->copy()->subDays(7);
        $from30d = $now->copy()->subDays(30);

        $leadsBase = LeadFormSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenantId);

        $leadsTotal7d = (clone $leadsBase)->where('submitted_at', '>=', $from7d)->count();
        $leadsConverted7d = (clone $leadsBase)
            ->where('submitted_at', '>=', $from7d)
            ->where('status', LeadFormSubmission::STATUS_CONVERTED)
            ->count();
        $leadsIgnored7d = (clone $leadsBase)
            ->where('submitted_at', '>=', $from7d)
            ->where('status', LeadFormSubmission::STATUS_IGNORED)
            ->count();
        $leadsNew7d = (clone $leadsBase)
            ->where('submitted_at', '>=', $from7d)
            ->where('status', LeadFormSubmission::STATUS_NEW)
            ->count();
        $leadsTotal30d = (clone $leadsBase)->where('submitted_at', '>=', $from30d)->count();

        $conversionRate7d = $leadsTotal7d > 0
            ? round(($leadsConverted7d / $leadsTotal7d) * 100, 1)
            : 0.0;

        $topProducts = DealProduct::withoutGlobalScopes()
            ->join('items', 'items.id', '=', 'deal_products.item_id')
            ->join('deals', 'deals.id', '=', 'deal_products.deal_id')
            ->where('deal_products.tenant_id', $tenantId)
            ->where('deals.tenant_id', $tenantId)
            ->whereIn('deals.stage', $openStages)
            ->groupBy('deal_products.item_id', 'items.name')
            ->orderByDesc(DB::raw('SUM(deal_products.total_value)'))
            ->limit(5)
            ->get([
                'deal_products.item_id',
                'items.name',
                DB::raw('SUM(deal_products.quantity) as quantity_total'),
                DB::raw('SUM(deal_products.total_value) as value_total'),
                DB::raw('COUNT(DISTINCT deal_products.deal_id) as deals_count'),
            ])
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'name' => (string) $row->name,
                'quantity_total' => (float) $row->quantity_total,
                'value_total' => (float) $row->value_total,
                'deals_count' => (int) $row->deals_count,
                'link' => route('deals.product-stats.show', ['item' => (int) $row->item_id]),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'summary' => [
                'deals_active_count' => $dealsActiveCount,
                'pipeline_total' => $pipelineTotal,
                'follow_ups_due_today' => $followUpsDueToday,
                'proposals_sent_week' => $proposalsSentWeek,
            ],
            'pipeline' => $pipeline,
            'agenda' => [
                'upcoming' => $upcoming,
                'overdue' => $overdue,
            ],
            'automations' => [
                'stalled_deals_count' => $stalledDealsCount,
                'recent_executions_count' => $recentExecutionsCount,
                'unread_notifications_count' => $unreadNotificationsCount,
                'recent_executions' => $recentExecutions,
            ],
            'leads' => [
                'total_7d' => $leadsTotal7d,
                'converted_7d' => $leadsConverted7d,
                'ignored_7d' => $leadsIgnored7d,
                'new_7d' => $leadsNew7d,
                'total_30d' => $leadsTotal30d,
                'conversion_rate_7d' => $conversionRate7d,
            ],
            'top_products' => $topProducts,
        ]);
    }
}

