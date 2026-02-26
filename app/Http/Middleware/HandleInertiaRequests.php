<?php

namespace App\Http\Middleware;

use App\Models\CompanySetting;
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
            ],
            'company' => $this->companyData($request),
            'tenant' => $this->tenantData($request),
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
}

