<?php

namespace App\Http\Requests\Maintenance;

use App\Models\MaintenanceDue;
use Illuminate\Foundation\Http\FormRequest;

class RemindMaintenanceDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $due = $this->route('maintenanceDue');

        return $due instanceof MaintenanceDue
            && $this->user()?->can('remind', $due) === true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
