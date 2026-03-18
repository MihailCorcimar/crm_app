<?php

namespace App\Policies;

use App\Models\AiSalesSuggestion;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResources;

class AiSalesSuggestionPolicy
{
    use AuthorizesTenantResources;

    public function viewAny(User $user): bool
    {
        return $this->canAccessActiveTenant($user, 'chat', 'read');
    }

    public function view(User $user, AiSalesSuggestion $suggestion): bool
    {
        return $this->canAccessTenantRecord($user, (int) $suggestion->tenant_id, 'chat', 'read')
            && (int) $suggestion->user_id === (int) $user->id;
    }

    public function update(User $user, AiSalesSuggestion $suggestion): bool
    {
        return $this->canAccessTenantRecord($user, (int) $suggestion->tenant_id, 'chat', 'update')
            && (int) $suggestion->user_id === (int) $user->id;
    }
}
