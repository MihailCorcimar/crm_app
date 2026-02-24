<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use App\Support\TenantOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantOnboardingController extends Controller
{
    public function __construct(
        private TenantOnboardingService $onboardingService
    ) {}

    public function show(Request $request): Response
    {
        $tenant = $this->activeTenant($request);

        $this->onboardingService->bootstrap($tenant);
        $tenant->load(['owner:id,name,email', 'members:id,name,email']);

        return Inertia::render('tenants/Onboarding', [
            'tenantDetails' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'owner' => [
                    'id' => $tenant->owner?->id,
                    'name' => $tenant->owner?->name,
                    'email' => $tenant->owner?->email,
                ],
                'members' => $tenant->members->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => (string) $member->pivot?->role,
                    'can_create_tenants' => (bool) $member->pivot?->can_create_tenants,
                ])->values()->all(),
            ],
            'settings' => $this->onboardingService->settings($tenant),
            'checklist' => $this->onboardingService->checklist($tenant),
            'canManageOnboarding' => $request->user()?->can('manageOnboarding', $tenant) ?? false,
            'canManageMembers' => $request->user()?->can('authorizeMember', $tenant) ?? false,
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('manageOnboarding', $tenant);

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'brand_primary_color' => ['required', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
        ]);

        $this->onboardingService->updateSettings($tenant, [
            'brand_name' => trim((string) $validated['brand_name']),
            'brand_primary_color' => strtoupper(trim((string) $validated['brand_primary_color'])),
        ]);

        return to_route('tenants.onboarding.show');
    }

    public function updatePermissions(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('manageOnboarding', $tenant);

        $validated = $request->validate([
            'default_user_role' => ['required', Rule::in(['member', 'manager'])],
            'allow_member_invites' => ['nullable', 'boolean'],
        ]);

        $this->onboardingService->updateSettings($tenant, [
            'default_user_role' => (string) $validated['default_user_role'],
            'allow_member_invites' => $request->boolean('allow_member_invites'),
        ]);

        return to_route('tenants.onboarding.show');
    }

    public function addMember(Request $request): RedirectResponse
    {
        $tenant = $this->activeTenant($request);
        $this->authorize('authorizeMember', $tenant);

        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
            'can_create_tenants' => ['nullable', 'boolean'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $member = User::query()->where('email', $email)->firstOrFail();
        $actor = $request->user();
        $defaultRole = $this->resolveDefaultRole($tenant);
        $canCreateTenants = $actor !== null
            && $tenant->isOwner($actor)
            && $request->boolean('can_create_tenants');

        $isAlreadyMember = $tenant->members()
            ->where('users.id', $member->id)
            ->exists();

        if (! $isAlreadyMember) {
            $tenant->members()->attach($member->id, [
                'role' => $defaultRole,
                'can_create_tenants' => $canCreateTenants,
            ]);
        } else {
            $tenant->members()->updateExistingPivot($member->id, [
                'can_create_tenants' => $canCreateTenants,
            ]);
        }

        return to_route('tenants.onboarding.show');
    }

    private function activeTenant(Request $request): Tenant
    {
        $tenantId = TenantContext::id($request);
        abort_if($tenantId === null, 422, 'An active tenant is required.');

        $tenant = $request->user()
            ?->tenants()
            ->with(['owner:id,name,email'])
            ->where('tenants.id', $tenantId)
            ->first();

        abort_if($tenant === null, 403, 'You are not authorized for the active tenant.');

        return $tenant;
    }

    private function resolveDefaultRole(Tenant $tenant): string
    {
        $settings = $this->onboardingService->settings($tenant);
        $defaultRole = strtolower(trim((string) ($settings['default_user_role'] ?? 'member')));

        return in_array($defaultRole, ['member', 'manager'], true)
            ? $defaultRole
            : 'member';
    }
}
