<?php

namespace App\Policies;

use App\Models\User;
use App\Models\StoryComment;
use Illuminate\Auth\Access\HandlesAuthorization;

class StoryCommentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the comment.
     */
    public function view(User $user, StoryComment $comment): bool
    {
        return true;
    }

    /**
     * Determine if the user can create comments.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can update the comment.
     */
    public function update(User $user, StoryComment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isStaff();
    }

    /**
     * Determine if the user can delete the comment.
     */
    public function delete(User $user, StoryComment $comment): bool
    {
        return $user->id === $comment->user_id || $user->isStaff();
    }
}
