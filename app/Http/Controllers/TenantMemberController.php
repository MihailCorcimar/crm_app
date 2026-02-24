<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tenants\TenantMemberStoreRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantOnboardingService;
use Illuminate\Http\RedirectResponse;

class TenantMemberController extends Controller
{
    public function __construct(
        private TenantOnboardingService $onboardingService
    ) {}

    public function store(TenantMemberStoreRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('authorizeMember', $tenant);

        $member = User::query()->findOrFail((int) $request->validated('member_user_id'));
        $actor = $request->user();
        $defaultRole = $this->resolveDefaultRole($tenant);

        $tenant->members()->attach($member->id, [
            'role' => $defaultRole,
            'can_create_tenants' => $actor !== null
                && $tenant->isOwner($actor)
                && $request->boolean('can_create_tenants'),
        ]);

        return to_route('tenants.show', $tenant);
    }

    public function destroy(Tenant $tenant, User $user): RedirectResponse
    {
        $this->authorize('removeMember', $tenant);

        if ($tenant->isOwner($user)) {
            return to_route('tenants.show', $tenant)
                ->withErrors(['member' => 'The owner cannot be removed from the tenant.']);
        }

        $tenant->members()->detach($user->id);

        return to_route('tenants.show', $tenant);
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
