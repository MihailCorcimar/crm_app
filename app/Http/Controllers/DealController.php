<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealQuickActivityRequest;
use App\Http\Requests\DealRequest;
use App\Http\Requests\DealStageRequest;
use App\Models\CalendarEvent;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\User;
use App\Support\DealStageService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct(private readonly DealStageService $dealStageService)
    {
        $this->authorizeResource(Deal::class, 'deal');
    }

    public function index(Request $request): Response
    {
        $tenantId = TenantContext::id($request) ?? 0;

        $validated = $request->validate([
            'owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->whereExists(function ($subQuery) use ($tenantId): void {
                        $subQuery
                            ->select(DB::raw(1))
                            ->from('tenant_user')
                            ->whereColumn('tenant_user.user_id', 'users.id')
                            ->where('tenant_user.tenant_id', $tenantId);
                    });
                }),
            ],
            'expected_close_from' => ['nullable', 'date'],
            'expected_close_to' => ['nullable', 'date', 'after_or_equal:expected_close_from'],
            'value_min' => ['nullable', 'numeric', 'min:0'],
            'value_max' => ['nullable', 'numeric', 'gte:value_min'],
        ]);

        $stageOptions = $this->stageOptions();
        $filters = $this->normalizedFilters($validated);

        $deals = Deal::query()
            ->with(['entity:id,name', 'owner:id,name'])
            ->when(
                $filters['owner_id'] !== null,
                fn ($query) => $query->where('owner_id', $filters['owner_id'])
            )
            ->when(
                $filters['expected_close_from'] !== null,
                fn ($query) => $query->whereDate('expected_close_date', '>=', $filters['expected_close_from'])
            )
            ->when(
                $filters['expected_close_to'] !== null,
                fn ($query) => $query->whereDate('expected_close_date', '<=', $filters['expected_close_to'])
            )
            ->when(
                $filters['value_min'] !== null,
                fn ($query) => $query->where('value', '>=', $filters['value_min'])
            )
            ->when(
                $filters['value_max'] !== null,
                fn ($query) => $query->where('value', '<=', $filters['value_max'])
            )
            ->orderByDesc('updated_at')
            ->get();

        $columns = collect($stageOptions)
            ->map(function (array $stageOption) use ($deals): array {
                $stageDeals = $deals
                    ->filter(fn (Deal $deal): bool => $deal->stage === $stageOption['value'])
                    ->values();

                $cards = $stageDeals
                    ->map(fn (Deal $deal): array => $this->dealCardPayload($deal))
                    ->all();

                return [
                    'stage' => $stageOption['value'],
                    'label' => $stageOption['label'],
                    'count' => count($cards),
                    'total_value' => (float) $stageDeals->sum(fn (Deal $deal): float => (float) $deal->value),
                    'deals' => $cards,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('deals/Index', [
            'columns' => $columns,
            'stageOptions' => $stageOptions,
            'owners' => $this->owners(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('deals/Create', [
            'entities' => $this->entities(),
            'owners' => $this->owners(),
            'stageOptions' => $this->stageOptions(),
            'defaultOwnerId' => auth()->id(),
        ]);
    }

    public function store(DealRequest $request): RedirectResponse
    {
        Deal::query()->create($this->payload($request->validated()));

        return to_route('deals.index');
    }

    public function show(Deal $deal): Response
    {
        $deal->load(['entity:id,name', 'owner:id,name']);

        return Inertia::render('deals/Show', [
            'deal' => [
                'id' => $deal->id,
                'title' => $deal->title,
                'entity' => $deal->entity?->name,
                'stage' => $deal->stage,
                'value' => (float) $deal->value,
                'probability' => (int) $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'owner' => $deal->owner?->name,
                'created_at' => $deal->created_at?->format('d/m/Y H:i'),
                'updated_at' => $deal->updated_at?->format('d/m/Y H:i'),
            ],
            'timeline' => $this->timeline($deal),
            'quickActivityTypes' => $this->quickActivityTypes(),
            'quickActivityDefaults' => [
                'activity_type' => 'call',
                'activity_at' => now()->format('Y-m-d\TH:i'),
                'owner_id' => auth()->id(),
            ],
            'owners' => $this->owners(),
        ]);
    }

    public function edit(Deal $deal): Response
    {
        return Inertia::render('deals/Edit', [
            'deal' => [
                'id' => $deal->id,
                'entity_id' => $deal->entity_id,
                'title' => $deal->title,
                'stage' => $deal->stage,
                'value' => (float) $deal->value,
                'probability' => (int) $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'owner_id' => $deal->owner_id,
            ],
            'entities' => $this->entities(),
            'owners' => $this->owners(),
            'stageOptions' => $this->stageOptions(),
        ]);
    }

    public function update(DealRequest $request, Deal $deal): RedirectResponse
    {
        $deal->update($this->payload($request->validated()));

        return to_route('deals.index');
    }

    public function updateStage(DealStageRequest $request, Deal $deal): RedirectResponse
    {
        $deal->update([
            'stage' => $request->string('stage')->toString(),
        ]);

        return back();
    }

    public function storeQuickActivity(DealQuickActivityRequest $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validated();
        $activityType = (string) $validated['activity_type'];
        $startAt = CarbonImmutable::parse((string) $validated['activity_at']);
        $duration = $this->quickActivityDuration($activityType);
        $endAt = $startAt->addMinutes($duration);
        $title = trim((string) ($validated['title'] ?? ''));

        CalendarEvent::query()->create([
            'title' => $title !== '' ? $title : $this->quickActivityDefaultTitle($activityType, $deal),
            'description' => $validated['description'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'location' => null,
            'owner_id' => (int) $validated['owner_id'],
            'eventable_type' => Deal::class,
            'eventable_id' => $deal->id,
            'calendar_type_id' => null,
            'calendar_action_id' => null,
            'status' => 'active',
            // Keep legacy columns synchronized while they exist.
            'event_date' => $startAt->format('Y-m-d'),
            'event_time' => $startAt->format('H:i:s'),
            'duration_minutes' => $duration,
            'user_id' => (int) $validated['owner_id'],
            'entity_id' => $deal->entity_id,
            'share' => null,
            'knowledge' => $activityType,
        ]);

        return to_route('deals.show', $deal);
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $deal->delete();

        return to_route('deals.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'entity_id' => $validated['entity_id'] ?? null,
            'person_id' => null,
            'title' => $validated['title'],
            'stage' => $validated['stage'],
            'value' => $validated['value'],
            'probability' => $validated['probability'],
            'expected_close_date' => $validated['expected_close_date'] ?? null,
            'owner_id' => $validated['owner_id'],
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function entities(): array
    {
        return Entity::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Entity $entity): array => [
                'id' => $entity->id,
                'name' => $entity->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function owners(): array
    {
        $tenantId = TenantContext::id();

        if (! is_int($tenantId) || $tenantId <= 0) {
            return [];
        }

        return User::query()
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function stageOptions(): array
    {
        return collect($this->dealStageService->forTenant(TenantContext::id()))
            ->map(fn (array $stage): array => [
                'value' => (string) $stage['value'],
                'label' => (string) $stage['label'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{owner_id: int|null, expected_close_from: string|null, expected_close_to: string|null, value_min: float|null, value_max: float|null}
     */
    private function normalizedFilters(array $validated): array
    {
        return [
            'owner_id' => isset($validated['owner_id']) ? (int) $validated['owner_id'] : null,
            'expected_close_from' => isset($validated['expected_close_from']) ? (string) $validated['expected_close_from'] : null,
            'expected_close_to' => isset($validated['expected_close_to']) ? (string) $validated['expected_close_to'] : null,
            'value_min' => isset($validated['value_min']) ? (float) $validated['value_min'] : null,
            'value_max' => isset($validated['value_max']) ? (float) $validated['value_max'] : null,
        ];
    }

    /**
     * @return array{id: int, title: string, entity: string|null, stage: string, value: float, probability: int, expected_close_date: string|null, owner: string|null}
     */
    private function dealCardPayload(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'entity' => $deal->entity?->name,
            'stage' => $deal->stage,
            'value' => (float) $deal->value,
            'probability' => (int) $deal->probability,
            'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
            'owner' => $deal->owner?->name,
        ];
    }

    /**
     * @return array<int, array{key: string, entry_type: string, activity_type: string|null, title: string, details: string, owner: string|null, occurred_at: string}>
     */
    private function timeline(Deal $deal): array
    {
        $items = [
            [
                'key' => sprintf('deal-created-%d', $deal->id),
                'entry_type' => 'negocio',
                'activity_type' => null,
                'title' => 'Negócio criado',
                'details' => sprintf('Registo criado com etapa "%s".', $this->stageLabel($deal->stage)),
                'owner' => $deal->owner?->name,
                'occurred_at' => $deal->created_at?->format('d/m/Y H:i') ?? '-',
                'sort_at' => $deal->created_at?->getTimestamp() ?? 0,
            ],
        ];

        if ($deal->updated_at !== null && $deal->updated_at->ne($deal->created_at)) {
            $items[] = [
                'key' => sprintf('deal-updated-%d', $deal->id),
                'entry_type' => 'negocio',
                'activity_type' => null,
                'title' => 'Negócio atualizado',
                'details' => 'Foram registadas alterações no negócio.',
                'owner' => $deal->owner?->name,
                'occurred_at' => $deal->updated_at->format('d/m/Y H:i'),
                'sort_at' => $deal->updated_at->getTimestamp(),
            ];
        }

        $activityItems = CalendarEvent::query()
            ->where('eventable_type', Deal::class)
            ->where('eventable_id', $deal->id)
            ->with('owner:id,name')
            ->orderByDesc('start_at')
            ->limit(100)
            ->get()
            ->map(function (CalendarEvent $event): array {
                $activityType = is_string($event->knowledge) ? $event->knowledge : null;
                $timestamp = $event->start_at ?? $event->created_at;

                return [
                    'key' => sprintf('event-%d', $event->id),
                    'entry_type' => 'atividade',
                    'activity_type' => $activityType,
                    'title' => trim((string) ($event->title ?? '')) !== '' ? (string) $event->title : 'Atividade',
                    'details' => trim((string) ($event->description ?? '')) !== '' ? (string) $event->description : 'Sem descrição.',
                    'owner' => $event->owner?->name,
                    'occurred_at' => $timestamp?->format('d/m/Y H:i') ?? '-',
                    'sort_at' => $timestamp?->getTimestamp() ?? 0,
                ];
            })
            ->all();

        $timeline = collect(array_merge($items, $activityItems))
            ->sortByDesc('sort_at')
            ->values()
            ->map(function (array $item): array {
                unset($item['sort_at']);

                return $item;
            })
            ->all();

        return $timeline;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function quickActivityTypes(): array
    {
        return [
            ['value' => 'call', 'label' => 'Chamada'],
            ['value' => 'task', 'label' => 'Tarefa'],
            ['value' => 'meeting', 'label' => 'Reunião'],
            ['value' => 'note', 'label' => 'Nota'],
        ];
    }

    private function quickActivityDuration(string $activityType): int
    {
        return match ($activityType) {
            'task' => 30,
            'meeting' => 60,
            'note' => 5,
            default => 20,
        };
    }

    private function quickActivityDefaultTitle(string $activityType, Deal $deal): string
    {
        $label = match ($activityType) {
            'call' => 'Chamada',
            'task' => 'Tarefa',
            'meeting' => 'Reunião',
            'note' => 'Nota',
            default => 'Atividade',
        };

        return sprintf('%s - %s', $label, $deal->title);
    }

    private function stageLabel(string $stage): string
    {
        return collect($this->stageOptions())
            ->firstWhere('value', $stage)['label'] ?? $stage;
    }
}
