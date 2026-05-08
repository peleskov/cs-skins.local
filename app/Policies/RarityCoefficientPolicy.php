<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RarityCoefficient;
use Illuminate\Auth\Access\HandlesAuthorization;

class RarityCoefficientPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RarityCoefficient');
    }

    public function view(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('View:RarityCoefficient');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RarityCoefficient');
    }

    public function update(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('Update:RarityCoefficient');
    }

    public function delete(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('Delete:RarityCoefficient');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RarityCoefficient');
    }

    public function restore(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('Restore:RarityCoefficient');
    }

    public function forceDelete(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('ForceDelete:RarityCoefficient');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RarityCoefficient');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RarityCoefficient');
    }

    public function replicate(AuthUser $authUser, RarityCoefficient $rarityCoefficient): bool
    {
        return $authUser->can('Replicate:RarityCoefficient');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RarityCoefficient');
    }

}