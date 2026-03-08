<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $tenant->members()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $user->canCreateTenants();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->members()
            ->where('users.id', $user->id)
            ->where(function ($query): void {
                $query->where('tenant_user.role', 'owner')
                    ->orWhere('tenant_user.can_create_tenants', true);
            })
            ->exists();
    }

    public function authorizeMember(User $user, Tenant $tenant): bool
    {
        if ($tenant->isOwner($user)) {
            return true;
        }

        $isMember = $tenant->members()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isMember) {
            return false;
        }

        return $this->allowMemberInvites($tenant);
    }

    public function removeMember(User $user, Tenant $tenant): bool
    {
        return $tenant->isOwner($user);
    }

    public function manageOnboarding(User $user, Tenant $tenant): bool
    {
        return $tenant->members()
            ->where('users.id', $user->id)
            ->where(function ($query): void {
                $query->where('tenant_user.role', 'owner')
                    ->orWhere('tenant_user.can_create_tenants', true);
            })
            ->exists();
    }

    public function manageBilling(User $user, Tenant $tenant): bool
    {
        return $tenant->members()
            ->where('users.id', $user->id)
            ->where(function ($query): void {
                $query->where('tenant_user.role', 'owner')
                    ->orWhere('tenant_user.can_create_tenants', true);
            })
            ->exists();
    }

    private function allowMemberInvites(Tenant $tenant): bool
    {
        $tenant->loadMissing('setting:id,tenant_id,settings');

        return (bool) data_get($tenant->setting?->settings, 'allow_member_invites', false);
    }
}
