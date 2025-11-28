<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Message;
use Illuminate\Auth\Access\HandlesAuthorization;

class MessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the message.
     */
    public function view(User $user, Message $message): bool
    {
        // All authenticated users can view messages
        return true;
    }

    /**
     * Determine if the user can create messages.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can update the message.
     */
    public function update(User $user, Message $message): bool
    {
        // Owner or staff can update
        return $user->id === $message->user_id || $user->isStaff();
    }

    /**
     * Determine if the user can delete the message.
     */
    public function delete(User $user, Message $message): bool
    {
        // Owner or staff can delete
        return $user->id === $message->user_id || $user->isStaff();
    }
}
