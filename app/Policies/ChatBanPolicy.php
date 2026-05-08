<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ChatBan;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChatBanPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ChatBan');
    }

    public function view(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('View:ChatBan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ChatBan');
    }

    public function update(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('Update:ChatBan');
    }

    public function delete(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('Delete:ChatBan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ChatBan');
    }

    public function restore(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('Restore:ChatBan');
    }

    public function forceDelete(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('ForceDelete:ChatBan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ChatBan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ChatBan');
    }

    public function replicate(AuthUser $authUser, ChatBan $chatBan): bool
    {
        return $authUser->can('Replicate:ChatBan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ChatBan');
    }

}