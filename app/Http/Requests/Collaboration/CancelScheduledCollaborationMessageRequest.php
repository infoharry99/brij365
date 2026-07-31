<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CollaborationMessage;
use Illuminate\Foundation\Http\FormRequest;

class CancelScheduledCollaborationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $message = $this->route('collaborationMessage');

        return $message instanceof CollaborationMessage
            && ($this->user()?->can('cancelScheduled', $message) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
