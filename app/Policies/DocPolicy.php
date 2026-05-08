<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Doc;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Doc');
    }

    public function view(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('View:Doc');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Doc');
    }

    public function update(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('Update:Doc');
    }

    public function delete(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('Delete:Doc');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Doc');
    }

    public function restore(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('Restore:Doc');
    }

    public function forceDelete(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('ForceDelete:Doc');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Doc');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Doc');
    }

    public function replicate(AuthUser $authUser, Doc $doc): bool
    {
        return $authUser->can('Replicate:Doc');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Doc');
    }

}