<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\AutomationNotification;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\DealAutomationRule;
use App\Models\DealAutomationRuleExecution;
use App\Models\DealEmailLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DealAutomationEngineService
{
    /**
     * @return array{rules_processed: int, deals_scanned: int, activities_created: int, notifications_created: int}
     */
    public function run(?int $tenantId = null, int $dealLimitPerRule = 300): array
    {
        $rules = DealAutomationRule::withoutGlobalScopes()
            ->where('status', DealAutomationRule::STATUS_ACTIVE)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->get();

        $summary = [
            'rules_processed' => 0,
            'deals_scanned' => 0,
            'activities_created' => 0,
            'notifications_created' => 0,
        ];

        foreach ($rules as $rule) {
            $summary['rules_processed']++;
            $result = $this->processRule($rule, $dealLimitPerRule);
            $summary['deals_scanned'] += $result['deals_scanned'];
            $summary['activities_created'] += $result['activities_created'];
            $summary['notifications_created'] += $result['notifications_created'];
        }

        return $summary;
    }

    /**
     * @return array{deals_scanned: int, activities_created: int, notifications_created: int}
     */
    private function processRule(DealAutomationRule $rule, int $dealLimitPerRule): array
    {
        $deals = Deal::withoutGlobalScopes()
            ->where('tenant_id', $rule->tenant_id)
            ->whereNotIn('stage', [Deal::STAGE_WON, Deal::STAGE_LOST])
            ->orderBy('updated_at')
            ->limit(max(1, $dealLimitPerRule))
            ->get();

        $createdActivities = 0;
        $createdNotifications = 0;

        foreach ($deals as $deal) {
            $latestActivityAt = $this->latestActivityAt((int) $deal->tenant_id, $deal);
            $anchor = $latestActivityAt->setTimezone((string) config('app.timezone', 'UTC'));

            if (! $this->isInactiveEnough($anchor, (int) $rule->inactivity_days)) {
                continue;
            }

            if ($this->alreadyTriggeredForAnchor($rule, $deal, $anchor)) {
                continue;
            }

            $ownerId = is_numeric($deal->owner_id) ? (int) $deal->owner_id : null;
            if ($ownerId === null) {
                $this->logExecution($rule, $deal, $anchor, null, 'skipped', 'Deal has no owner.');

                continue;
            }

            $event = $this->createAutomationActivity($rule, $deal, $ownerId, $anchor);
            $this->logExecution($rule, $deal, $anchor, $event->id, 'created', null);
            $createdActivities++;

            if ($rule->notify_internal) {
                $this->createInternalNotification($rule, $deal, $event, $ownerId, $anchor);
                $createdNotifications++;
            }
        }

        return [
            'deals_scanned' => $deals->count(),
            'activities_created' => $createdActivities,
            'notifications_created' => $createdNotifications,
        ];
    }

    private function createAutomationActivity(
        DealAutomationRule $rule,
        Deal $deal,
        int $ownerId,
        CarbonImmutable $anchor
    ): CalendarEvent {
        $startAt = $this->buildPlannedStartAt((int) $rule->activity_due_in_days);
        $duration = match ($rule->activity_type) {
            'meeting' => 45,
            'call' => 20,
            'note' => 10,
            default => 30,
        };

        $endAt = $startAt->addMinutes($duration);
        $priorityLabel = strtoupper((string) $rule->activity_priority);

        $title = $this->renderTemplate(
            (string) $rule->activity_title_template,
            $deal,
            $anchor
        );

        $descriptionTemplate = (string) ($rule->activity_description_template ?? '');
        $descriptionBody = $descriptionTemplate !== ''
            ? $this->renderTemplate($descriptionTemplate, $deal, $anchor)
            : 'Atividade gerada automaticamente por regra de inatividade.';

        $description = trim(implode("\n", array_filter([
            $descriptionBody,
            sprintf('Prioridade: %s', $priorityLabel),
            sprintf('Regra: %s', $rule->name),
            sprintf('Ultima atividade: %s', $anchor->format('d/m/Y H:i')),
        ])));

        return CalendarEvent::withoutGlobalScopes()->create([
            'tenant_id' => $deal->tenant_id,
            'title' => $title,
            'description' => $description,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => null,
            'owner_id' => $ownerId,
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'status' => 'active',
            'event_date' => $startAt->format('Y-m-d'),
            'event_time' => $startAt->format('H:i:s'),
            'duration_minutes' => $duration,
            'share' => null,
            'knowledge' => sprintf('automation:%s:priority_%s', $rule->activity_type, $rule->activity_priority),
            'user_id' => $ownerId,
            'calendar_type_id' => null,
            'calendar_action_id' => null,
            'entity_id' => $deal->entity_id,
        ]);
    }

    private function createInternalNotification(
        DealAutomationRule $rule,
        Deal $deal,
        CalendarEvent $event,
        int $ownerId,
        CarbonImmutable $anchor
    ): void {
        $messageTemplate = trim((string) ($rule->notification_message ?? ''));
        $message = $messageTemplate !== ''
            ? $this->renderTemplate($messageTemplate, $deal, $anchor)
            : sprintf(
                'Foi criada uma atividade automatica para o negocio "%s" devido a inatividade.',
                $deal->title
            );

        AutomationNotification::withoutGlobalScopes()->create([
            'tenant_id' => $deal->tenant_id,
            'user_id' => $ownerId,
            'deal_automation_rule_id' => $rule->id,
            'deal_id' => $deal->id,
            'calendar_event_id' => $event->id,
            'title' => 'Nova atividade automatica',
            'message' => $message,
            'read_at' => null,
        ]);

        ActivityLog::withoutGlobalScopes()->create([
            'tenant_id' => $deal->tenant_id,
            'occurred_at' => now(),
            'user_id' => $ownerId,
            'menu' => 'Automações',
            'action' => 'Alerta automacao',
            'device' => 'system',
            'ip_address' => '127.0.0.1',
            'method' => 'SYSTEM',
            'path' => 'automations/rules/run',
            'user_agent' => 'scheduler',
        ]);
    }

    private function logExecution(
        DealAutomationRule $rule,
        Deal $deal,
        CarbonImmutable $anchor,
        ?int $calendarEventId,
        string $status,
        ?string $statusReason
    ): void {
        DealAutomationRuleExecution::withoutGlobalScopes()->create([
            'tenant_id' => $deal->tenant_id,
            'deal_automation_rule_id' => $rule->id,
            'deal_id' => $deal->id,
            'owner_id' => is_numeric($deal->owner_id) ? (int) $deal->owner_id : null,
            'calendar_event_id' => $calendarEventId,
            'activity_anchor_at' => $anchor->format('Y-m-d H:i:s'),
            'triggered_at' => now(),
            'status' => $status,
            'status_reason' => $statusReason,
            'meta' => [
                'inactivity_days_rule' => (int) $rule->inactivity_days,
            ],
        ]);
    }

    private function alreadyTriggeredForAnchor(DealAutomationRule $rule, Deal $deal, CarbonImmutable $anchor): bool
    {
        return DealAutomationRuleExecution::withoutGlobalScopes()
            ->where('tenant_id', $deal->tenant_id)
            ->where('deal_automation_rule_id', $rule->id)
            ->where('deal_id', $deal->id)
            ->where('activity_anchor_at', $anchor->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function isInactiveEnough(CarbonImmutable $latestActivityAt, int $inactivityDays): bool
    {
        $days = $latestActivityAt->diffInDays(CarbonImmutable::now($latestActivityAt->timezone));

        return $days >= max(1, $inactivityDays);
    }

    private function buildPlannedStartAt(int $dueInDays): CarbonImmutable
    {
        $now = CarbonImmutable::now('Europe/Lisbon');
        $planned = $now->addDays(max(0, $dueInDays))->setTime(10, 0, 0);

        if ($planned->isWeekend()) {
            while ($planned->isWeekend()) {
                $planned = $planned->addDay()->setTime(10, 0, 0);
            }
        }

        return $planned->setTimezone((string) config('app.timezone', 'UTC'));
    }

    private function renderTemplate(string $template, Deal $deal, CarbonImmutable $latestActivityAt): string
    {
        $dealTitle = trim((string) $deal->title);
        $entityName = trim((string) ($deal->entity?->name ?? 'cliente'));
        $ownerName = trim((string) ($deal->owner?->name ?? 'responsavel'));
        $daysWithoutActivity = $latestActivityAt->diffInDays(CarbonImmutable::now($latestActivityAt->timezone));

        return strtr($template, [
            '{deal_title}' => $dealTitle !== '' ? $dealTitle : 'negocio',
            '{entity_name}' => $entityName !== '' ? $entityName : 'cliente',
            '{owner_name}' => $ownerName !== '' ? $ownerName : 'responsavel',
            '{days_without_activity}' => (string) $daysWithoutActivity,
        ]);
    }

    private function latestActivityAt(int $tenantId, Deal $deal): CarbonImmutable
    {
        $eventAt = CalendarEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($deal): void {
                $query->where(function ($sub) use ($deal): void {
                    $sub->where('eventable_type', Deal::class)
                        ->where('eventable_id', $deal->id);
                });

                if ($deal->entity_id !== null) {
                    $query->orWhere('entity_id', $deal->entity_id);
                }
            })
            ->max('start_at');

        $emailAt = DealEmailLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('deal_id', $deal->id)
            ->max('sent_at');

        $timestamps = collect([$eventAt, $emailAt, $deal->updated_at, $deal->created_at])
            ->filter()
            ->map(fn ($value) => CarbonImmutable::parse($value))
            ->sortDesc()
            ->values();

        /** @var CarbonImmutable|null $latest */
        $latest = $timestamps->first();

        return $latest ?? CarbonImmutable::now((string) config('app.timezone', 'UTC'));
    }
}
