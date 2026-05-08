<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VirtualItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class VirtualItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VirtualItem');
    }

    public function view(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('View:VirtualItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VirtualItem');
    }

    public function update(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('Update:VirtualItem');
    }

    public function delete(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('Delete:VirtualItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:VirtualItem');
    }

    public function restore(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('Restore:VirtualItem');
    }

    public function forceDelete(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('ForceDelete:VirtualItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VirtualItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VirtualItem');
    }

    public function replicate(AuthUser $authUser, VirtualItem $virtualItem): bool
    {
        return $authUser->can('Replicate:VirtualItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VirtualItem');
    }

}