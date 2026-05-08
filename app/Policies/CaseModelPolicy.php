<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CaseModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class CaseModelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CaseModel');
    }

    public function view(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('View:CaseModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CaseModel');
    }

    public function update(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('Update:CaseModel');
    }

    public function delete(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('Delete:CaseModel');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CaseModel');
    }

    public function restore(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('Restore:CaseModel');
    }

    public function forceDelete(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('ForceDelete:CaseModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CaseModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CaseModel');
    }

    public function replicate(AuthUser $authUser, CaseModel $caseModel): bool
    {
        return $authUser->can('Replicate:CaseModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CaseModel');
    }

}