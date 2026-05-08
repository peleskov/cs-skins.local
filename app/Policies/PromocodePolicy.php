<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Promocode;
use Illuminate\Auth\Access\HandlesAuthorization;

class PromocodePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Promocode');
    }

    public function view(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('View:Promocode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Promocode');
    }

    public function update(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('Update:Promocode');
    }

    public function delete(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('Delete:Promocode');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Promocode');
    }

    public function restore(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('Restore:Promocode');
    }

    public function forceDelete(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('ForceDelete:Promocode');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Promocode');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Promocode');
    }

    public function replicate(AuthUser $authUser, Promocode $promocode): bool
    {
        return $authUser->can('Replicate:Promocode');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Promocode');
    }

}