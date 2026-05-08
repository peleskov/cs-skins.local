<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BalanceWithdrawRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class BalanceWithdrawRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BalanceWithdrawRequest');
    }

    public function view(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('View:BalanceWithdrawRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BalanceWithdrawRequest');
    }

    public function update(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('Update:BalanceWithdrawRequest');
    }

    public function delete(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('Delete:BalanceWithdrawRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:BalanceWithdrawRequest');
    }

    public function restore(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('Restore:BalanceWithdrawRequest');
    }

    public function forceDelete(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('ForceDelete:BalanceWithdrawRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BalanceWithdrawRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BalanceWithdrawRequest');
    }

    public function replicate(AuthUser $authUser, BalanceWithdrawRequest $balanceWithdrawRequest): bool
    {
        return $authUser->can('Replicate:BalanceWithdrawRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BalanceWithdrawRequest');
    }

}