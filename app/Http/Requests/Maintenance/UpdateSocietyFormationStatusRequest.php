<?php

namespace App\Http\Requests\Maintenance;

use App\Models\SocietyFormation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSocietyFormationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $formation = $this->route('societyFormation');

        return $formation instanceof SocietyFormation
            && $this->user()?->can('update', $formation) === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['draft', 'application_filed', 'in_progress', 'formed', 'handed_over', 'blocked'])],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'current_stage' => ['nullable', 'string', 'max:120'],
            'next_step' => ['nullable', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
