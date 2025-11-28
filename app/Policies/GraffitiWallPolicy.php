<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GraffitiWall;
use Illuminate\Auth\Access\HandlesAuthorization;

class GraffitiWallPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view graffiti.
     */
    public function view(User $user, GraffitiWall $graffiti): bool
    {
        return true;
    }

    /**
     * Determine if the user can create graffiti.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can update the graffiti.
     */
    public function update(User $user, GraffitiWall $graffiti): bool
    {
        return $user->id === $graffiti->user_id || $user->isStaff();
    }

    /**
     * Determine if the user can delete the graffiti.
     */
    public function delete(User $user, GraffitiWall $graffiti): bool
    {
        return $user->id === $graffiti->user_id || $user->isStaff();
    }
}
