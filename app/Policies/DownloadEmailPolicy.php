<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DownloadEmail;
use Illuminate\Auth\Access\HandlesAuthorization;

class DownloadEmailPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user is 'super_admin' and if he is bypass controls.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_download::email');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('view_download::email');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_download::email');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('update_download::email');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('delete_download::email');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_download::email');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('force_delete_download::email');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_download::email');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('restore_download::email');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_download::email');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, DownloadEmail $downloadEmail): bool
    {
        return $user->can('replicate_download::email');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_download::email');
    }
}
