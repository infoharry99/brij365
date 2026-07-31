<?php

namespace App\Http\Requests\Notifications;

use App\Models\UserNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAllNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', UserNotification::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['unread', 'read', 'archived'])],
            'category' => ['nullable', 'string', 'max:64'],
            'severity' => ['nullable', 'string', Rule::in(['info', 'success', 'warning', 'critical'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'created_before' => ['nullable', 'date'],
        ];
    }
}
