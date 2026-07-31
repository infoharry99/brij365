<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class RemoveChatConversationMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('chatConversation');
        $targetUser = $this->route('user');

        if (is_numeric($conversation) || is_string($conversation)) {
            $conversation = ChatConversation::query()->find($conversation);
        }

        if (is_numeric($targetUser) || is_string($targetUser)) {
            $targetUser = User::query()->find($targetUser);
        }

        if (! $conversation instanceof ChatConversation || ! $targetUser instanceof User) {
            return false;
        }

        if ($conversation->type === 'direct_message') {
            return false;
        }

        $actor = $this->user();
        if (! $actor) {
            return false;
        }

        $isSelf = (int) $targetUser->id === (int) $actor->id;
        $isOwner = (int) $conversation->owner_user_id === (int) $actor->id;
        $canManage = $actor->can('manageMembers', $conversation);
        $hasGlobalAccess = app(\App\Services\Collaboration\ChatAccessService::class)->can($actor, 'can_manage_members') || $actor->hasPermission('*');

        return $isSelf || $isOwner || $canManage || $hasGlobalAccess;
    }

    public function rules(): array
    {
        return [];
    }
}
