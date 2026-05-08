<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CaseItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class CaseItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CaseItem');
    }

    public function view(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('View:CaseItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CaseItem');
    }

    public function update(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('Update:CaseItem');
    }

    public function delete(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('Delete:CaseItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CaseItem');
    }

    public function restore(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('Restore:CaseItem');
    }

    public function forceDelete(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('ForceDelete:CaseItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CaseItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CaseItem');
    }

    public function replicate(AuthUser $authUser, CaseItem $caseItem): bool
    {
        return $authUser->can('Replicate:CaseItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CaseItem');
    }

}