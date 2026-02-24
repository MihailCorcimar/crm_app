<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ItemRequest;
use App\Models\Item;
use App\Models\VatRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(): Response
    {
        $items = Item::query()
            ->with('vatRate:id,name,rate')
            ->orderBy('reference')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Item $item): array => [
                'id' => $item->id,
                'reference' => $item->reference ?: $item->code,
                'name' => $item->name ?: $item->description,
                'description' => $item->description,
                'price' => (float) $item->price,
                'vat_rate_id' => $item->vat_rate_id,
                'vat_rate' => $item->vatRate?->name,
                'vat_rate_value' => $item->vatRate?->rate !== null ? (float) $item->vatRate->rate : (float) $item->vat,
                'photo_url' => $item->photo_path ? route('settings.items.photo', $item) : null,
                'notes' => $item->notes,
                'status' => $item->status,
            ]);

        return Inertia::render('settings/Items', [
            'items' => $items,
            'vatRates' => $this->vatRates(),
        ]);
    }

    public function store(ItemRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $item = Item::query()->create([
            'reference' => $validated['reference'],
            'code' => $validated['reference'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'vat_rate_id' => $validated['vat_rate_id'],
            'vat' => $this->vatRateValue((int) $validated['vat_rate_id']),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->storePhotoIfPresent($request, $item);

        return to_route('settings.items.index');
    }

    public function update(ItemRequest $request, Item $item): RedirectResponse
    {
        $validated = $request->validated();

        $item->update([
            'reference' => $validated['reference'],
            'code' => $validated['reference'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'vat_rate_id' => $validated['vat_rate_id'],
            'vat' => $this->vatRateValue((int) $validated['vat_rate_id']),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->storePhotoIfPresent($request, $item);

        return to_route('settings.items.index');
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->proposalLines()->exists() || $item->orderLines()->exists() || $item->supplierOrderLines()->exists()) {
            return to_route('settings.items.index')
                ->withErrors(['item' => 'Cannot delete an item linked to proposals or orders.']);
        }

        if ($item->photo_path) {
            Storage::disk('local')->delete($item->photo_path);
        }

        $item->delete();

        return to_route('settings.items.index');
    }

    public function photo(Item $item)
    {
        abort_unless($item->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($item->photo_path), 404);

        return Storage::disk('local')->response($item->photo_path);
    }

    private function vatRateValue(int $vatRateId): float
    {
        return (float) VatRate::query()->whereKey($vatRateId)->value('rate');
    }

    private function storePhotoIfPresent(Request $request, Item $item): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        if ($item->photo_path) {
            Storage::disk('local')->delete($item->photo_path);
        }

        $path = $request->file('photo')->store('private/item-photos', 'local');
        $item->update(['photo_path' => $path]);
    }

    /**
     * @return array<int, array{id: int, name: string, rate: float}>
     */
    private function vatRates(): array
    {
        return VatRate::query()
            ->where('status', 'active')
            ->orderBy('rate')
            ->get(['id', 'name', 'rate'])
            ->map(fn (VatRate $vatRate): array => [
                'id' => $vatRate->id,
                'name' => $vatRate->name,
                'rate' => (float) $vatRate->rate,
            ])
            ->all();
    }
}
