<?php

namespace App\Policies;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Collaboration\ChatAccessService;
use App\Services\Security\CompanyScopeService;

class ChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return app(ChatAccessService::class)->can($user, 'can_view');
    }

    public function view(User $user, ChatConversation $conversation): bool
    {
        return $this->viewAny($user)
            && app(CompanyScopeService::class)->allows($user, $conversation->company_id)
            && $conversation->isMember($user);
    }

    public function create(User $user): bool
    {
        $access = app(ChatAccessService::class);

        return $access->can($user, 'can_view')
            && (
                $access->can($user, 'can_create_dm')
                || $access->can($user, 'can_create_group')
                || $access->can($user, 'can_create_channel')
            );
    }

    public function post(User $user, ChatConversation $conversation): bool
    {
        $membership = $conversation->membershipFor($user);

        return $this->view($user, $conversation)
            && $conversation->status === 'active'
            && $membership !== null
            && $membership->can_post
            && $membership->archived_at === null
            && app(ChatAccessService::class)->can($user, 'can_post');
    }

    public function manageMembers(User $user, ChatConversation $conversation): bool
    {
        $membership = $conversation->membershipFor($user);

        return $this->view($user, $conversation)
            && (
                $user->hasPermission('*')
                || app(ChatAccessService::class)->can($user, 'can_manage_members')
                || ($membership !== null && $membership->can_manage_members)
            );
    }

    public function archive(User $user, ChatConversation $conversation): bool
    {
        return $this->view($user, $conversation)
            && app(ChatAccessService::class)->can($user, 'can_archive');
    }
}
