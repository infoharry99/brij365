<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CollaborationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollaborationMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('collaborationMessage');

        return $message instanceof CollaborationMessage
            && ($this->user()?->can('react', $message) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', 'max:16', 'regex:/^[^\s<>{}]+$/u'],
            'action' => ['required', 'string', Rule::in(['add', 'remove', 'toggle'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $message = $this->route('collaborationMessage');

                if (! $message instanceof CollaborationMessage || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! in_array($message->status, ['unread', 'read', 'archived'], true)) {
                    $validator->errors()->add('message', 'Only delivered mailbox messages can receive reactions.');
                }
            },
        ];
    }
}
