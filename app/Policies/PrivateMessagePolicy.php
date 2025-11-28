<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PrivateMessage;
use Illuminate\Auth\Access\HandlesAuthorization;

class PrivateMessagePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the private message.
     */
    public function view(User $user, PrivateMessage $message): bool
    {
        // Only sender or recipient can view
        return $user->id === $message->sender_id || $user->id === $message->recipient_id;
    }

    /**
     * Determine if the user can create private messages.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can delete the private message.
     */
    public function delete(User $user, PrivateMessage $message): bool
    {
        // Sender can delete their sent message, recipient can delete from inbox
        // Staff can delete any
        return $user->id === $message->sender_id 
            || $user->id === $message->recipient_id 
            || $user->isStaff();
    }
}
