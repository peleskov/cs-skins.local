<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Upgrade;
use Illuminate\Auth\Access\HandlesAuthorization;

class UpgradePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Upgrade');
    }

    public function view(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('View:Upgrade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Upgrade');
    }

    public function update(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('Update:Upgrade');
    }

    public function delete(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('Delete:Upgrade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Upgrade');
    }

    public function restore(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('Restore:Upgrade');
    }

    public function forceDelete(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('ForceDelete:Upgrade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Upgrade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Upgrade');
    }

    public function replicate(AuthUser $authUser, Upgrade $upgrade): bool
    {
        return $authUser->can('Replicate:Upgrade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Upgrade');
    }

}