<?php

namespace App\Http\Requests\Notifications;

use App\Models\UserNotification;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NotificationIndexRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['q', 'status', 'category', 'severity', 'date_from', 'date_to', 'page', 'per_page'],
                );
            },
        ];
    }
}
