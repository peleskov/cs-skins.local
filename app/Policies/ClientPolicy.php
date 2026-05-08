<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Client');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Client');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Client');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Client');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Client');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Client');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Client');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Client');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Client');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Client');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Client');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Client');
    }

}