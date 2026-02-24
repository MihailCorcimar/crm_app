<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompanySettingRequest;
use App\Models\CompanySetting;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingController extends Controller
{
    public function index(): Response
    {
        $company = $this->companySetting();

        return Inertia::render('settings/Company', [
            'company' => [
                'name' => $company->name,
                'address' => $company->address,
                'postal_code' => $company->postal_code,
                'city' => $company->city,
                'tax_number' => $company->tax_number,
                'logo_url' => $company->logo_path ? route('settings.company.logo') : null,
            ],
        ]);
    }

    public function update(CompanySettingRequest $request): RedirectResponse
    {
        $company = $this->companySetting();
        $validated = $request->validated();

        $company->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'city' => $validated['city'] ?? null,
            'tax_number' => $validated['tax_number'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }

            $logoPath = $request->file('logo')->store('private/company', 'local');
            $company->update(['logo_path' => $logoPath]);
        }

        return to_route('settings.company.index');
    }

    public function logo()
    {
        $company = $this->companySetting();

        abort_unless($company->logo_path, 404);
        abort_unless(Storage::disk('local')->exists($company->logo_path), 404);

        return Storage::disk('local')->response($company->logo_path);
    }

    private function companySetting(): CompanySetting
    {
        $tenantId = TenantContext::id(request());
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $company = CompanySetting::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['name' => 'App de Gestao']
        );

        $name = trim((string) $company->name);
        if ($name === '' || in_array($name, ['Laravel', 'Laravel Starter Kit'], true)) {
            $company->update(['name' => 'App de Gestao']);
            $company->refresh();
        }

        return $company;
    }
}
