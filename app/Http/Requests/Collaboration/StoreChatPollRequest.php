<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Services\Collaboration\ChatAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChatPollRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChatConversation|null $conversation */
        $conversation = $this->route('chatConversation');

        return $conversation instanceof ChatConversation
            && ($this->user()?->can('post', $conversation) ?? false)
            && app(ChatAccessService::class)->can($this->user(), 'can_create_poll');
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*' => ['required', 'string', 'max:120', 'distinct'],
            'allows_multiple' => ['nullable', 'boolean'],
            'closes_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
