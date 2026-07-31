<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('chatMessage')?->conversation;

        return $conversation && ($this->user()?->can('view', $conversation) ?? false);
    }

    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:16'],
            'action' => ['nullable', 'in:toggle,add,remove'],
        ];
    }
}
