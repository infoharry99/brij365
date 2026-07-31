<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('archive', $this->route('chatConversation')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
