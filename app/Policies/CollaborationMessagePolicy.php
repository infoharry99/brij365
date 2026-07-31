<?php

namespace App\Policies;

use App\Models\CollaborationMessage;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class CollaborationMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('collaboration.view')
            || $user->hasPermission('collaboration.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function view(User $user, CollaborationMessage $message): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $message->company_id)
            && $message->isParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('collaboration.manage')
            || $user->hasPermission('employee.self_service');
    }

    public function markRead(User $user, CollaborationMessage $message): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $message->company_id)
            && (int) $message->recipient_user_id === (int) $user->id
            && in_array($message->status, ['unread', 'read'], true);
    }

    public function archive(User $user, CollaborationMessage $message): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $message->company_id)
            && (int) $message->recipient_user_id === (int) $user->id
            && $message->status !== 'scheduled';
    }

    public function cancelScheduled(User $user, CollaborationMessage $message): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $message->company_id)
            && (int) $message->sender_user_id === (int) $user->id
            && $message->status === 'scheduled';
    }

    public function linkCrm(User $user, CollaborationMessage $message): bool
    {
        return $this->view($user, $message)
            && ! $user->hasPermission('partner.portal')
            && ! $user->hasPermission('buyer.view');
    }

    public function updateMailboxState(User $user, CollaborationMessage $message): bool
    {
        return $this->view($user, $message)
            && ! $user->hasPermission('partner.portal')
            && ! $user->hasPermission('buyer.view');
    }

    public function react(User $user, CollaborationMessage $message): bool
    {
        return $this->updateMailboxState($user, $message)
            && in_array($message->status, ['unread', 'read', 'archived'], true);
    }
}
