<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    public function view(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }

    public function update(User $user, Business $business): bool
    {
        return $business->user_id === $user->id;
    }
}
