<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenants\TenantStoreRequest;
use App\Http\Requests\Tenants\TenantUpdateRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\TenantOnboardingService;
use App\Support\TenantSubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private TenantOnboardingService $onboardingService,
        private TenantSubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = $request->user()
            ->tenants()
            ->with(['owner:id,name,email', 'setting:id,tenant_id,settings'])
            ->orderBy('name')
            ->get()
            ->map(function (Tenant $tenant): array {
                $checklist = $this->onboardingService->checklist($tenant);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'owner' => $tenant->owner?->name,
                    'role' => (string) $tenant->pivot?->role,
                    'can_create_tenants' => (bool) $tenant->pivot?->can_create_tenants,
                    'settings' => $tenant->setting?->settings ?? [],
                    'onboarding' => [
                        'completion_rate' => $checklist['completion_rate'],
                        'is_complete' => $checklist['is_complete'],
                    ],
                    'can_manage_billing' => (string) $tenant->pivot?->role === 'owner'
                        || (bool) $tenant->pivot?->can_create_tenants,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('tenants/Index', [
            'tenants' => $tenants,
            'canCreateTenant' => $request->user()->can('create', Tenant::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Tenant::class);

        return Inertia::render('tenants/Create', [
            'defaults' => [
                'brand_name' => '',
                'brand_primary_color' => '#1F2937',
                'default_user_role' => 'member',
                'allow_member_invites' => false,
            ],
            'canCreateTenant' => $request->user()->can('create', Tenant::class),
        ]);
    }

    public function store(TenantStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $createdTenant = null;

        DB::transaction(function () use ($validated, $user, &$createdTenant): void {
            $tenant = Tenant::query()->create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'owner_user_id' => $user->id,
            ]);

            $tenant->members()->attach($user->id, [
                'role' => 'owner',
                'can_create_tenants' => true,
            ]);

            TenantSetting::query()->create([
                'tenant_id' => $tenant->id,
                'settings' => $validated['settings'] ?? [],
            ]);

            $this->onboardingService->bootstrap($tenant, $validated['settings'] ?? []);
            $this->subscriptionService->ensureSubscription($tenant, $user);

            $createdTenant = $tenant;
        });

        if ($createdTenant !== null) {
            $request->session()->put('current_tenant_id', $createdTenant->id);
            $user->forceFill(['current_tenant_id' => $createdTenant->id])->save();
        }

        return to_route('tenants.index');
    }

    public function show(Tenant $tenant, Request $request): Response
    {
        $this->authorize('view', $tenant);

        $tenant->load(['owner:id,name,email', 'setting:id,tenant_id,settings', 'members:id,name,email']);

        return Inertia::render('tenants/Show', [
            'tenantDetails' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'owner' => [
                    'id' => $tenant->owner?->id,
                    'name' => $tenant->owner?->name,
                    'email' => $tenant->owner?->email,
                ],
                'settings' => $tenant->setting?->settings ?? [],
                'members' => $tenant->members->map(fn ($member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => (string) $member->pivot?->role,
                    'can_create_tenants' => (bool) $member->pivot?->can_create_tenants,
                ])->values()->all(),
                'onboarding' => $this->onboardingService->checklist($tenant),
            ],
            'canManageMembers' => $request->user()->can('authorizeMember', $tenant),
            'canManageOnboarding' => $request->user()->can('manageOnboarding', $tenant),
            'canManageBilling' => $request->user()->can('manageBilling', $tenant),
            'canEditTenant' => $request->user()->can('update', $tenant),
        ]);
    }

    public function edit(Tenant $tenant, Request $request): Response
    {
        $this->authorize('update', $tenant);

        $settings = $tenant->setting?->settings ?? [];

        return Inertia::render('tenants/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'settings' => [
                    'brand_name' => (string) ($settings['brand_name'] ?? ''),
                    'brand_primary_color' => (string) ($settings['brand_primary_color'] ?? '#1F2937'),
                    'default_user_role' => (string) ($settings['default_user_role'] ?? 'member'),
                    'allow_member_invites' => (bool) ($settings['allow_member_invites'] ?? false),
                ],
            ],
            'canEditTenant' => $request->user()->can('update', $tenant),
        ]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($tenant, $validated): void {
            $tenant->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
            ]);

            TenantSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                ['settings' => $validated['settings'] ?? []],
            );
        });

        return to_route('tenants.show', $tenant);
    }

    public function switchTenant(Tenant $tenant, Request $request): RedirectResponse
    {
        $this->authorize('view', $tenant);

        $validated = $request->validate([
            'remember' => ['nullable', 'boolean'],
        ]);

        $request->session()->put('current_tenant_id', $tenant->id);

        if (($validated['remember'] ?? true) === true) {
            $request->user()->forceFill(['current_tenant_id' => $tenant->id])->save();
        }

        return back();
    }
}
