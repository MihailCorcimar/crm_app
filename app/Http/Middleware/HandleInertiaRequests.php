<?php

namespace App\Http\Middleware;

use App\Models\AutomationNotification;
use App\Models\CalendarEvent;
use App\Models\CompanySetting;
use App\Models\Deal;
use App\Models\LeadFormSubmission;
use App\Models\PermissionGroup;
use App\Support\DealStageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => $this->defaultAppName(),
            'auth' => [
                'user' => $request->user(),
                'module_permissions' => $this->modulePermissions($request),
            ],
            'company' => $this->companyData($request),
            'tenant' => $this->tenantData($request),
            'automation_notifications' => $this->automationNotifications($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{name: string, logo_url: string|null}
     */
    private function companyData(Request $request): array
    {
        if (! Schema::hasTable('company_settings')) {
            return [
                'name' => $this->defaultAppName(),
                'logo_url' => '/images/logo.png',
            ];
        }

        $company = CompanySetting::query()->first();
        $name = trim((string) ($company?->name ?? ''));

        if ($name === '' || in_array($name, ['Laravel', 'Laravel Starter Kit'], true)) {
            $name = $this->defaultAppName();
        }

        $tenantId = data_get($request->attributes->get('tenantContext'), 'active.id');
        $logoUrl = '/images/logo.png';

        if ($request->user() && $company?->logo_path) {
            $logoUrl = route('settings.company.logo', array_filter([
                'tenant' => is_numeric($tenantId) ? (int) $tenantId : null,
                'v' => $company->updated_at?->getTimestamp(),
            ], static fn ($value): bool => $value !== null));
        }

        return [
            'name' => $name,
            'logo_url' => $logoUrl,
        ];
    }

    private function defaultAppName(): string
    {
        return 'CRM';
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function modulePermissions(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }

        $defaultActionPermissions = [];
        foreach (array_keys(PermissionGroup::ACTIONS) as $action) {
            $defaultActionPermissions[$action] = true;
        }

        $defaultPermissions = [];
        foreach (array_keys(PermissionGroup::MODULES) as $module) {
            $defaultPermissions[$module] = $defaultActionPermissions;
        }

        if (! is_numeric($user->permission_group_id)) {
            return $defaultPermissions;
        }

        $group = $user->relationLoaded('permissionGroup')
            ? $user->permissionGroup
            : $user->permissionGroup()->first();

        if (! $group instanceof PermissionGroup) {
            return $defaultPermissions;
        }

        return $group->permissionsMatrix();
    }

    /**
     * @return array{
     *   active: array{id: int, name: string, slug: string, brand_primary_color: string}|null,
     *   available: array<int, array{id: int, name: string, slug: string, brand_primary_color: string}>
     * }
     */
    private function tenantData(Request $request): array
    {
        /** @var array{
         *   active: array{id: int, name: string, slug: string, brand_primary_color: string}|null,
         *   available: array<int, array{id: int, name: string, slug: string, brand_primary_color: string}>
         * }|null $context
         */
        $context = $request->attributes->get('tenantContext');

        if ($context !== null) {
            return $context;
        }

        return [
            'active' => null,
            'available' => [],
        ];
    }

    /**
     * @return array{
     *   unread_count: int,
     *   categories: array<int, array{
     *      key: string,
     *      label: string,
     *      unread_count: int,
     *      items: array<int, array{
     *          id: string,
     *          source_id: int|null,
     *          title: string,
     *          message: string|null,
     *          href: string|null,
     *          action_label: string|null,
     *          read_at: string|null,
     *          created_at: string|null,
     *          can_mark_read: bool
     *      }>
     *   }>
     * }
     */
    private function automationNotifications(Request $request): array
    {
        if (! $request->user()) {
            return [
                'unread_count' => 0,
                'categories' => [],
            ];
        }

        $tenantId = data_get($request->attributes->get('tenantContext'), 'active.id');
        if (! is_numeric($tenantId)) {
            return [
                'unread_count' => 0,
                'categories' => [],
            ];
        }

        $userId = (int) $request->user()->getAuthIdentifier();
        $tenantId = (int) $tenantId;
        $now = CarbonImmutable::now('Europe/Lisbon');

        $categories = [
            $this->automationCategory($tenantId, $userId),
            $this->dealsCategory($tenantId, $userId, $now),
            $this->calendarCategory($tenantId, $userId, $now),
            $this->leadFormsCategory($tenantId),
        ];

        $unreadCount = (int) collect($categories)->sum(
            fn (array $category): int => (int) ($category['unread_count'] ?? 0)
        );

        return [
            'unread_count' => $unreadCount,
            'categories' => $categories,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   unread_count: int,
     *   items: array<int, array{
     *      id: string,
     *      source_id: int|null,
     *      title: string,
     *      message: string|null,
     *      href: string|null,
     *      action_label: string|null,
     *      read_at: string|null,
     *      created_at: string|null,
     *      can_mark_read: bool
     *   }>
     * }
     */
    private function automationCategory(int $tenantId, int $userId): array
    {
        if (! Schema::hasTable('automation_notifications')) {
            return [
                'key' => 'automations',
                'label' => 'Automações',
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $items = AutomationNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByRaw('read_at IS NULL DESC')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn (AutomationNotification $notification): array => [
                'id' => 'automations-'.$notification->id,
                'source_id' => (int) $notification->id,
                'title' => (string) $notification->title,
                'message' => $notification->message !== null ? (string) $notification->message : null,
                'href' => $notification->deal_id !== null ? route('deals.show', (int) $notification->deal_id) : null,
                'action_label' => $notification->deal_id !== null ? 'Abrir negócio' : null,
                'read_at' => $notification->read_at?->format('d/m/Y H:i'),
                'created_at' => $notification->created_at?->format('d/m/Y H:i'),
                'can_mark_read' => true,
            ])
            ->values()
            ->all();

        $unreadCount = AutomationNotification::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return [
            'key' => 'automations',
            'label' => 'Automações',
            'unread_count' => (int) $unreadCount,
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   unread_count: int,
     *   items: array<int, array{
     *      id: string,
     *      source_id: int|null,
     *      title: string,
     *      message: string|null,
     *      href: string|null,
     *      action_label: string|null,
     *      read_at: string|null,
     *      created_at: string|null,
     *      can_mark_read: bool
     *   }>
     * }
     */
    private function dealsCategory(int $tenantId, int $userId, CarbonImmutable $now): array
    {
        if (! Schema::hasTable('deals')) {
            return [
                'key' => 'deals',
                'label' => 'Negócios',
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $stageLabels = collect(app(DealStageService::class)->forTenant($tenantId))
            ->mapWithKeys(fn (array $stage): array => [(string) $stage['value'] => (string) $stage['label']])
            ->all();

        $limitDate = $now->addDays(3)->toDateString();

        $items = Deal::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->whereNotIn('stage', [Deal::STAGE_WON, Deal::STAGE_LOST])
            ->whereNotNull('expected_close_date')
            ->whereDate('expected_close_date', '<=', $limitDate)
            ->orderBy('expected_close_date')
            ->limit(6)
            ->get(['id', 'title', 'stage', 'expected_close_date', 'updated_at'])
            ->map(function (Deal $deal) use ($stageLabels): array {
                $expected = $deal->expected_close_date?->format('d/m/Y') ?? '-';
                $stageLabel = (string) ($stageLabels[(string) $deal->stage] ?? (string) $deal->stage);

                return [
                    'id' => 'deals-'.$deal->id,
                    'source_id' => (int) $deal->id,
                    'title' => 'Fecho próximo: '.$deal->title,
                    'message' => 'Etapa '.$stageLabel.' • Fecho previsto '.$expected,
                    'href' => route('deals.show', (int) $deal->id),
                    'action_label' => 'Abrir negócio',
                    'read_at' => null,
                    'created_at' => $deal->updated_at?->format('d/m/Y H:i'),
                    'can_mark_read' => false,
                ];
            })
            ->values()
            ->all();

        return [
            'key' => 'deals',
            'label' => 'Negócios',
            'unread_count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   unread_count: int,
     *   items: array<int, array{
     *      id: string,
     *      source_id: int|null,
     *      title: string,
     *      message: string|null,
     *      href: string|null,
     *      action_label: string|null,
     *      read_at: string|null,
     *      created_at: string|null,
     *      can_mark_read: bool
     *   }>
     * }
     */
    private function calendarCategory(int $tenantId, int $userId, CarbonImmutable $now): array
    {
        if (
            ! Schema::hasTable('calendar_events')
            || ! Schema::hasColumn('calendar_events', 'owner_id')
            || ! Schema::hasColumn('calendar_events', 'start_at')
        ) {
            return [
                'key' => 'calendar',
                'label' => 'Calendário',
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $windowStart = $now->subHours(2)->toDateTimeString();
        $windowEnd = $now->addDay()->toDateTimeString();

        $items = CalendarEvent::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $userId)
            ->where('status', 'active')
            ->whereNotNull('start_at')
            ->whereBetween('start_at', [$windowStart, $windowEnd])
            ->orderBy('start_at')
            ->limit(6)
            ->get(['id', 'title', 'location', 'start_at'])
            ->map(function (CalendarEvent $event) use ($now): array {
                $startAt = $event->start_at?->timezone('Europe/Lisbon');
                $isOverdue = $startAt !== null && $startAt->lt($now);
                $statusLabel = $isOverdue ? 'Em atraso' : 'Próximo';

                return [
                    'id' => 'calendar-'.$event->id,
                    'source_id' => (int) $event->id,
                    'title' => $statusLabel.': '.((string) ($event->title ?: 'Atividade no calendário')),
                    'message' => sprintf(
                        '%s%s',
                        $startAt?->format('d/m/Y H:i') ?? '-',
                        $event->location ? ' • '.$event->location : ''
                    ),
                    'href' => route('calendar.index'),
                    'action_label' => 'Abrir calendário',
                    'read_at' => null,
                    'created_at' => $startAt?->format('d/m/Y H:i'),
                    'can_mark_read' => false,
                ];
            })
            ->values()
            ->all();

        return [
            'key' => 'calendar',
            'label' => 'Calendário',
            'unread_count' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *   key: string,
     *   label: string,
     *   unread_count: int,
     *   items: array<int, array{
     *      id: string,
     *      source_id: int|null,
     *      title: string,
     *      message: string|null,
     *      href: string|null,
     *      action_label: string|null,
     *      read_at: string|null,
     *      created_at: string|null,
     *      can_mark_read: bool
     *   }>
     * }
     */
    private function leadFormsCategory(int $tenantId): array
    {
        if (
            ! Schema::hasTable('lead_form_submissions')
            || ! Schema::hasColumn('lead_form_submissions', 'status')
        ) {
            return [
                'key' => 'lead_forms',
                'label' => 'Formulários',
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $items = LeadFormSubmission::query()
            ->with(['leadForm:id,name'])
            ->where('tenant_id', $tenantId)
            ->where('status', LeadFormSubmission::STATUS_NEW)
            ->orderByDesc('submitted_at')
            ->limit(6)
            ->get(['id', 'lead_form_id', 'payload', 'submitted_at'])
            ->map(function (LeadFormSubmission $submission): array {
                $payload = is_array($submission->payload) ? $submission->payload : [];
                $name = trim((string) ($payload['full_name'] ?? $payload['name'] ?? ''));
                $email = trim((string) ($payload['email'] ?? ''));
                $leadFormName = $submission->leadForm?->name ?? 'Formulário público';

                $parts = array_filter([$name !== '' ? $name : null, $email !== '' ? $email : null]);
                $message = implode(' • ', $parts);
                if ($message === '') {
                    $message = 'Nova submissão pendente de tratamento.';
                }

                return [
                    'id' => 'lead_forms-'.$submission->id,
                    'source_id' => (int) $submission->id,
                    'title' => 'Nova lead: '.$leadFormName,
                    'message' => $message,
                    'href' => route('lead-forms.index'),
                    'action_label' => 'Abrir formulários',
                    'read_at' => null,
                    'created_at' => $submission->submitted_at?->format('d/m/Y H:i'),
                    'can_mark_read' => false,
                ];
            })
            ->values()
            ->all();

        $unreadCount = LeadFormSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('status', LeadFormSubmission::STATUS_NEW)
            ->count();

        return [
            'key' => 'lead_forms',
            'label' => 'Formulários',
            'unread_count' => (int) $unreadCount,
            'items' => $items,
        ];
    }
}

