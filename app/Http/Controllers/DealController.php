<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealRequest;
use App\Models\Deal;
use App\Models\Entity;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Deal::class, 'deal');
    }

    public function index(): Response
    {
        $deals = Deal::query()
            ->with(['entity:id,name', 'owner:id,name'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Deal $deal): array => [
                'id' => $deal->id,
                'title' => $deal->title,
                'entity' => $deal->entity?->name,
                'stage' => $deal->stage,
                'value' => (float) $deal->value,
                'probability' => (int) $deal->probability,
                'expected_close_date' => $deal->expected_close_date?->format('Y-m-d'),
                'owner' => $deal->owner?->name,
            ]);

        return Inertia::render('deals/Index', [
            'deals' => $deals,
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
        return [
            ['value' => 'lead', 'label' => 'Lead'],
            ['value' => 'proposal', 'label' => 'Proposta'],
            ['value' => 'negotiation', 'label' => 'Negociação'],
            ['value' => 'follow_up', 'label' => 'Follow Up'],
            ['value' => 'won', 'label' => 'Ganho'],
            ['value' => 'lost', 'label' => 'Perdido'],
        ];
    }
}
