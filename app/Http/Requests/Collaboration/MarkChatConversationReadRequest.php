<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Foundation\Http\FormRequest;

class MarkChatConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('chatConversation')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
