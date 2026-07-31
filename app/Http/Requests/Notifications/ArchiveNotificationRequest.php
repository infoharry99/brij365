<?php

namespace App\Http\Requests\Notifications;

use App\Models\UserNotification;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userNotification = $this->route('userNotification');

        return $userNotification instanceof UserNotification
            && ($this->user()?->can('update', $userNotification) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
