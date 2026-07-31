<?php

namespace App\Http\Requests\Notifications;

use App\Models\UserNotification;
use Illuminate\Foundation\Http\FormRequest;

class NotificationSummaryRequest extends FormRequest
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
        return [];
    }
}
