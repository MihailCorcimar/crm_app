<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\VatRateRequest;
use App\Models\VatRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VatRateController extends Controller
{
    public function index(): Response
    {
        $vatRates = VatRate::query()
            ->orderBy('rate')
            ->get(['id', 'name', 'rate', 'status'])
            ->map(fn (VatRate $vatRate): array => [
                'id' => $vatRate->id,
                'name' => $vatRate->name,
                'rate' => $vatRate->rate,
                'status' => $vatRate->status,
            ])
            ->all();

        return Inertia::render('settings/FinanceVatRates', [
            'vatRates' => $vatRates,
        ]);
    }

    public function store(VatRateRequest $request): RedirectResponse
    {
        VatRate::query()->create($request->validated());

        return to_route('settings.vat-rates.index');
    }

    public function update(VatRateRequest $request, VatRate $vatRate): RedirectResponse
    {
        $vatRate->update($request->validated());

        return to_route('settings.vat-rates.index');
    }

    public function destroy(VatRate $vatRate): RedirectResponse
    {
        if ($vatRate->items()->exists()) {
            return to_route('settings.vat-rates.index')
                ->withErrors(['vatRate' => 'Cannot delete a VAT rate linked to items.']);
        }

        $vatRate->delete();

        return to_route('settings.vat-rates.index');
    }
}
