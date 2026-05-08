<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ClientInventoryItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientInventoryItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClientInventoryItem');
    }

    public function view(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('View:ClientInventoryItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClientInventoryItem');
    }

    public function update(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('Update:ClientInventoryItem');
    }

    public function delete(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('Delete:ClientInventoryItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClientInventoryItem');
    }

    public function restore(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('Restore:ClientInventoryItem');
    }

    public function forceDelete(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('ForceDelete:ClientInventoryItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClientInventoryItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClientInventoryItem');
    }

    public function replicate(AuthUser $authUser, ClientInventoryItem $clientInventoryItem): bool
    {
        return $authUser->can('Replicate:ClientInventoryItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClientInventoryItem');
    }

}