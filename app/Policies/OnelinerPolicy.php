<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Oneliner;
use Illuminate\Auth\Access\HandlesAuthorization;

class OnelinerPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the oneliner.
     */
    public function view(User $user, Oneliner $oneliner): bool
    {
        return true;
    }

    /**
     * Determine if the user can create oneliners.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can update the oneliner.
     */
    public function update(User $user, Oneliner $oneliner): bool
    {
        return $user->id === $oneliner->user_id || $user->isStaff();
    }

    /**
     * Determine if the user can delete the oneliner.
     */
    public function delete(User $user, Oneliner $oneliner): bool
    {
        return $user->id === $oneliner->user_id || $user->isStaff();
    }

    /**
     * Determine if the user can moderate oneliners (approve/reject).
     */
    public function moderate(User $user): bool
    {
        return $user->isStaff();
    }
}
