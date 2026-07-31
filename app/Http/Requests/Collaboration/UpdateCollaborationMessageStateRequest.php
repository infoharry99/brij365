<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CollaborationMessage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollaborationMessageStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('collaborationMessage');

        return $message instanceof CollaborationMessage
            && ($this->user()?->can('updateMailboxState', $message) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['set_flags', 'set_labels', 'move', 'mark_read', 'mark_unread', 'snooze'])],
            'starred' => ['nullable', 'boolean'],
            'important' => ['nullable', 'boolean'],
            'labels' => ['required_if:action,set_labels', 'array', 'max:20'],
            'labels.*' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'],
            'folder' => ['required_if:action,move', 'nullable', 'string', Rule::in(['inbox', 'archived', 'spam', 'trash'])],
            'snoozed_until' => ['required_if:action,snooze', 'nullable', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $message = $this->route('collaborationMessage');
                $user = $this->user();

                if (! $user || ! $message instanceof CollaborationMessage || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if (in_array($this->input('action'), ['mark_read', 'mark_unread'], true)
                    && (int) $message->recipient_user_id !== (int) $user->id) {
                    $validator->errors()->add('action', 'Only the message recipient can change read or unread state.');
                }

                if ($this->input('action') === 'set_flags'
                    && ! $this->has('starred')
                    && ! $this->has('important')) {
                    $validator->errors()->add('starred', 'At least one flag must be supplied.');
                }

                if ($this->input('action') === 'snooze' && $this->filled('snoozed_until')) {
                    $snoozedUntil = Carbon::parse((string) $this->input('snoozed_until'));

                    if ($snoozedUntil->greaterThan(now()->addDays(90))) {
                        $validator->errors()->add('snoozed_until', 'Mailbox snooze cannot be scheduled more than 90 days ahead.');
                    }
                }
            },
        ];
    }
}
