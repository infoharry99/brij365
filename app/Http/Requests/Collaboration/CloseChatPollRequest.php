<?php

namespace App\Http\Requests\Collaboration;

use Illuminate\Foundation\Http\FormRequest;

class CloseChatPollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('poll')?->message?->conversation;

        return $conversation && ($this->user()?->can('view', $conversation) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
