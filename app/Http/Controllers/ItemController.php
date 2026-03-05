<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItemCatalogRequest;
use App\Models\Item;
use App\Models\VatRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Item::class, 'item');
    }

    public function index(Request $request): Response
    {
        $query = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();

        $items = Item::query()
            ->with('vatRate:id,name')
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($innerQuery) use ($query): void {
                    $innerQuery
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('reference', 'like', "%{$query}%")
                        ->orWhere('code', 'like', "%{$query}%");
                });
            })
            ->when(
                in_array($status, ['active', 'inactive'], true),
                fn ($builder) => $builder->where('status', $status)
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Item $item): array => [
                'id' => $item->id,
                'reference' => $item->reference,
                'code' => $item->code,
                'name' => $item->name,
                'price' => (float) $item->price,
                'vat' => (float) $item->vat,
                'status' => $item->status,
                'vat_rate_name' => $item->vatRate?->name,
            ]);

        return Inertia::render('items/Index', [
            'items' => $items,
            'filters' => [
                'q' => $query,
                'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('items/Create', [
            'vatRates' => $this->vatRates(),
        ]);
    }

    public function store(ItemCatalogRequest $request): RedirectResponse
    {
        Item::query()->create($this->payload($request->validated()));

        return to_route('items.index');
    }

    public function edit(Item $item): Response
    {
        return Inertia::render('items/Edit', [
            'item' => [
                'id' => $item->id,
                'reference' => $item->reference,
                'code' => $item->code,
                'name' => $item->name,
                'description' => $item->description,
                'price' => (float) $item->price,
                'vat' => (float) $item->vat,
                'vat_rate_id' => $item->vat_rate_id,
                'status' => $item->status,
                'notes' => $item->notes,
            ],
            'vatRates' => $this->vatRates(),
        ]);
    }

    public function update(ItemCatalogRequest $request, Item $item): RedirectResponse
    {
        $item->update($this->payload($request->validated()));

        return to_route('items.index');
    }

    public function deactivate(Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update([
            'status' => 'inactive',
        ]);

        return back();
    }

    public function activate(Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $item->update([
            'status' => 'active',
        ]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        return [
            'reference' => trim((string) $validated['reference']),
            'code' => trim((string) $validated['code']),
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'price' => (float) $validated['price'],
            'vat' => (float) $validated['vat'],
            'vat_rate_id' => $validated['vat_rate_id'] ?? null,
            'status' => (string) $validated['status'],
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, rate: float}>
     */
    private function vatRates(): array
    {
        return VatRate::query()
            ->where('status', 'active')
            ->orderBy('rate')
            ->orderBy('name')
            ->get(['id', 'name', 'rate'])
            ->map(fn (VatRate $vatRate): array => [
                'id' => $vatRate->id,
                'name' => $vatRate->name,
                'rate' => (float) $vatRate->rate,
            ])
            ->all();
    }
}
