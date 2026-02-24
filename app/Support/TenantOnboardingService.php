<?php

namespace App\Support;

use App\Models\CalendarAction;
use App\Models\CalendarType;
use App\Models\CompanySetting;
use App\Models\ContactRole;
use App\Models\Country;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\VatRate;

class TenantOnboardingService
{
    /**
     * @param  array<string, mixed>  $initialSettings
     */
    public function bootstrap(Tenant $tenant, array $initialSettings = []): void
    {
        TenantSetting::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['settings' => $initialSettings]
        );

        CompanySetting::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['name' => $tenant->name]
        );

        Country::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'PT'],
            ['name' => 'Portugal']
        );

        ContactRole::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Main Contact']
        );

        VatRate::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'IVA 23%'],
            ['rate' => 23, 'status' => 'active']
        );

        CalendarType::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'General'],
            ['status' => 'active']
        );

        CalendarAction::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Follow-up'],
            ['status' => 'active']
        );
    }

    /**
     * @return array{brand_name: string, brand_primary_color: string, default_user_role: string, allow_member_invites: bool}
     */
    public function settings(Tenant $tenant): array
    {
        /** @var array<string, mixed> $raw */
        $raw = TenantSetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->value('settings') ?? [];

        $defaultRole = strtolower(trim((string) ($raw['default_user_role'] ?? 'member')));
        if (! in_array($defaultRole, ['member', 'manager'], true)) {
            $defaultRole = 'member';
        }

        $color = strtoupper(trim((string) ($raw['brand_primary_color'] ?? '')));
        if (! preg_match('/^#[A-F0-9]{6}$/', $color)) {
            $color = '#1F2937';
        }

        return [
            'brand_name' => trim((string) ($raw['brand_name'] ?? '')),
            'brand_primary_color' => $color,
            'default_user_role' => $defaultRole,
            'allow_member_invites' => (bool) ($raw['allow_member_invites'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{brand_name: string, brand_primary_color: string, default_user_role: string, allow_member_invites: bool}
     */
    public function updateSettings(Tenant $tenant, array $attributes): array
    {
        $current = $this->settings($tenant);
        $payload = array_merge($current, $attributes);

        $setting = TenantSetting::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['settings' => $payload]
        );

        $setting->forceFill(['settings' => $payload])->save();

        return $this->settings($tenant);
    }

    /**
     * @return array{
     *     completion_rate: int,
     *     is_complete: bool,
     *     items: array<int, array{key: string, done: bool}>
     * }
     */
    public function checklist(Tenant $tenant): array
    {
        $settings = $this->settings($tenant);
        $membersCount = $tenant->members()->count();
        $companyName = trim((string) CompanySetting::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->value('name'));

        $hasBaseData = Country::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
            && ContactRole::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
            && VatRate::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
            && CalendarType::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists()
            && CalendarAction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();

        $items = [
            [
                'key' => 'branding',
                'done' => $settings['brand_name'] !== '' && preg_match('/^#[A-F0-9]{6}$/', $settings['brand_primary_color']) === 1,
            ],
            [
                'key' => 'users',
                'done' => $membersCount > 1,
            ],
            [
                'key' => 'permissions',
                'done' => in_array($settings['default_user_role'], ['member', 'manager'], true),
            ],
            [
                'key' => 'company_profile',
                'done' => $companyName !== '' && ! in_array($companyName, ['App de Gestao', 'Laravel', 'Laravel Starter Kit'], true),
            ],
            [
                'key' => 'base_data',
                'done' => $hasBaseData,
            ],
        ];

        $doneCount = collect($items)
            ->where('done', true)
            ->count();

        $total = count($items);
        $completionRate = $total > 0 ? (int) round(($doneCount / $total) * 100) : 0;

        return [
            'completion_rate' => $completionRate,
            'is_complete' => $total > 0 && $doneCount === $total,
            'items' => $items,
        ];
    }
}
