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
        $canManage = $actor->can('manageMembers', $conversation);

        return $isSelf || $canManage;
    }

    public function rules(): array
    {
        return [];
    }
}
