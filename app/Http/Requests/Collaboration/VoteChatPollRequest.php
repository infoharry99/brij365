<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatPoll;
use App\Services\Collaboration\ChatAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VoteChatPollRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChatPoll|null $poll */
        $poll = $this->route('poll');
        $conversation = $poll?->message?->conversation;

        return $poll instanceof ChatPoll
            && $conversation
            && ($this->user()?->can('view', $conversation) ?? false)
            && app(ChatAccessService::class)->can($this->user(), 'can_vote_poll');
    }

    public function rules(): array
    {
        return [
            'option_ids' => ['required', 'array', 'min:1', 'max:10'],
            'option_ids.*' => ['integer', Rule::exists('chat_poll_options', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ChatPoll|null $poll */
                $poll = $this->route('poll');
                if (! $poll || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($poll->status !== 'open' || ($poll->closes_at && $poll->closes_at->isPast())) {
                    $validator->errors()->add('option_ids', 'This poll is closed.');
                }

                $allowed = $poll->options()->pluck('id')->map(fn ($id) => (int) $id)->all();
                foreach ((array) $this->input('option_ids', []) as $optionId) {
                    if (! in_array((int) $optionId, $allowed, true)) {
                        $validator->errors()->add('option_ids', 'Select options from this poll only.');
                        break;
                    }
                }

                if (! $poll->allows_multiple && count((array) $this->input('option_ids', [])) > 1) {
                    $validator->errors()->add('option_ids', 'Select only one option for this poll.');
                }
            },
        ];
    }
}
