<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealRequest;
use App\Http\Requests\DealStageRequest;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\User;
use App\Support\DealStageService;
use App\Support\TenantContext;
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
}