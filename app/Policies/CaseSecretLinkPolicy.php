<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\CaseSecretLink;
use Illuminate\Auth\Access\HandlesAuthorization;

class CaseSecretLinkPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CaseSecretLink');
    }

    public function view(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('View:CaseSecretLink');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CaseSecretLink');
    }

    public function update(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('Update:CaseSecretLink');
    }

    public function delete(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('Delete:CaseSecretLink');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CaseSecretLink');
    }

    public function restore(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('Restore:CaseSecretLink');
    }

    public function forceDelete(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('ForceDelete:CaseSecretLink');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CaseSecretLink');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CaseSecretLink');
    }

    public function replicate(AuthUser $authUser, CaseSecretLink $caseSecretLink): bool
    {
        return $authUser->can('Replicate:CaseSecretLink');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CaseSecretLink');
    }

}