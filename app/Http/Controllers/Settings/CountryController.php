<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CountryController extends Controller
{
    public function index(): Response
    {
        $countries = Country::query()
            ->withCount('entities')
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Country $country): array => [
                'id' => $country->id,
                'code' => $country->code,
                'name' => $country->name,
                'entities_count' => $country->entities_count,
            ])
            ->all();

        return Inertia::render('settings/EntityCountries', [
            'countries' => $countries,
        ]);
    }

    public function store(CountryRequest $request): RedirectResponse
    {
        Country::query()->create($request->validated());

        return to_route('settings.countries.index');
    }

    public function update(CountryRequest $request, Country $country): RedirectResponse
    {
        $country->update($request->validated());

        return to_route('settings.countries.index');
    }

    public function destroy(Country $country): RedirectResponse
    {
        if ($country->entities()->exists()) {
            return to_route('settings.countries.index')
                ->withErrors(['country' => 'Nao podes eliminar um pais associado a entidades.']);
        }

        $country->delete();

        return to_route('settings.countries.index');
    }
}
