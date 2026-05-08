<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BonusTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class BonusTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BonusTransaction');
    }

    public function view(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('View:BonusTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BonusTransaction');
    }

    public function update(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('Update:BonusTransaction');
    }

    public function delete(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('Delete:BonusTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BonusTransaction');
    }

    public function restore(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('Restore:BonusTransaction');
    }

    public function forceDelete(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('ForceDelete:BonusTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BonusTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BonusTransaction');
    }

    public function replicate(AuthUser $authUser, BonusTransaction $bonusTransaction): bool
    {
        return $authUser->can('Replicate:BonusTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BonusTransaction');
    }

}