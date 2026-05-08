<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BannedWord;
use Illuminate\Auth\Access\HandlesAuthorization;

class BannedWordPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BannedWord');
    }

    public function view(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('View:BannedWord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BannedWord');
    }

    public function update(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('Update:BannedWord');
    }

    public function delete(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('Delete:BannedWord');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BannedWord');
    }

    public function restore(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('Restore:BannedWord');
    }

    public function forceDelete(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('ForceDelete:BannedWord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BannedWord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BannedWord');
    }

    public function replicate(AuthUser $authUser, BannedWord $bannedWord): bool
    {
        return $authUser->can('Replicate:BannedWord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BannedWord');
    }

}