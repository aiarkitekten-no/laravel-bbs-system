<?php

namespace App\Policies;

use App\Models\User;
use App\Models\File;
use Illuminate\Auth\Access\HandlesAuthorization;

class FilePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the file.
     */
    public function view(User $user, File $file): bool
    {
        // Approved files visible to all, pending only to uploader/staff
        if ($file->status === File::STATUS_APPROVED) {
            return true;
        }
        return $user->id === $file->uploader_id || $user->isStaff();
    }

    /**
     * Determine if the user can upload files.
     */
    public function create(User $user): bool
    {
        return !$user->isGuest();
    }

    /**
     * Determine if the user can download the file.
     */
    public function download(User $user, File $file): bool
    {
        if ($file->status !== File::STATUS_APPROVED) {
            return $user->id === $file->uploader_id || $user->isStaff();
        }
        return $file->canDownload($user);
    }

    /**
     * Determine if the user can update the file.
     */
    public function update(User $user, File $file): bool
    {
        return $user->id === $file->uploader_id || $user->isStaff();
    }

    /**
     * Determine if the user can delete the file.
     */
    public function delete(User $user, File $file): bool
    {
        return $user->id === $file->uploader_id || $user->isStaff();
    }

    /**
     * Determine if the user can approve files.
     */
    public function approve(User $user): bool
    {
        return $user->isStaff();
    }
}
