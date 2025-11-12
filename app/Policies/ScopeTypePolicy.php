<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ScopeType;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScopeTypePolicy
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
        return $user->can('view_any_scope::type');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ScopeType $scopeType): bool
    {
        return $user->can('view_scope::type');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_scope::type');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ScopeType $scopeType): bool
    {
        return $user->can('update_scope::type');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ScopeType $scopeType): bool
    {
        return $user->can('delete_scope::type');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_scope::type');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ScopeType $scopeType): bool
    {
        return $user->can('force_delete_scope::type');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_scope::type');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ScopeType $scopeType): bool
    {
        return $user->can('restore_scope::type');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_scope::type');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ScopeType $scopeType): bool
    {
        return $user->can('replicate_scope::type');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_scope::type');
    }
}
