<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;

class CancelCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('calendarEvent');

        return $event instanceof CalendarEvent && ($this->user()?->can('cancel', $event) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
