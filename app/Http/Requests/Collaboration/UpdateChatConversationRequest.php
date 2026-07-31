<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('chatConversation');

        if (! $conversation instanceof ChatConversation) {
            return false;
        }

        return $this->user()?->can('manageMembers', $conversation) ?? false;
    }

    public function rules(): array
    {
        $conversation = $this->route('chatConversation');
        $isDm = $conversation instanceof ChatConversation && $conversation->type === 'direct_message';

        return [
            'title' => [$isDm ? 'nullable' : 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
