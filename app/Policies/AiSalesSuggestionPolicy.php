<?php

namespace App\Policies;

use App\Models\AiSalesSuggestion;
use App\Models\User;

class AiSalesSuggestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    public function view(User $user, AiSalesSuggestion $suggestion): bool
    {
        return $suggestion->user_id === $user->id;
    }

    public function update(User $user, AiSalesSuggestion $suggestion): bool
    {
        return $suggestion->user_id === $user->id;
    }
}
