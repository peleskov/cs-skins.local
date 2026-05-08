<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CaseCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class CaseCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CaseCategory');
    }

    public function view(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('View:CaseCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CaseCategory');
    }

    public function update(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('Update:CaseCategory');
    }

    public function delete(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('Delete:CaseCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CaseCategory');
    }

    public function restore(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('Restore:CaseCategory');
    }

    public function forceDelete(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('ForceDelete:CaseCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CaseCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CaseCategory');
    }

    public function replicate(AuthUser $authUser, CaseCategory $caseCategory): bool
    {
        return $authUser->can('Replicate:CaseCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CaseCategory');
    }

}