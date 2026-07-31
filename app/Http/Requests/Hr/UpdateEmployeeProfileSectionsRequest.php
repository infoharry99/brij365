<?php

namespace App\Http\Requests\Hr;

use App\Models\EmployeeProfileSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEmployeeProfileSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return $employee && $this->user()?->can('update', $employee);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array'],
            'sections.personal' => ['sometimes', 'array'],
            'sections.personal.dob' => ['nullable', 'date', 'before:today'],
            'sections.personal.gender' => ['nullable', 'string', 'max:40'],
            'sections.personal.marital' => ['nullable', 'string', 'max:40'],
            'sections.personal.blood' => ['nullable', 'string', 'max:10'],
            'sections.personal.mobile' => ['nullable', 'string', 'max:30'],
            'sections.personal.email' => ['nullable', 'email', 'max:255'],
            'sections.emergency' => ['sometimes', 'array', 'max:10'],
            'sections.emergency.*.name' => ['required_with:sections.emergency', 'string', 'max:120'],
            'sections.emergency.*.relation' => ['nullable', 'string', 'max:80'],
            'sections.emergency.*.phone' => ['nullable', 'string', 'max:30'],
            'sections.family' => ['sometimes', 'array', 'max:20'],
            'sections.family.*.name' => ['required_with:sections.family', 'string', 'max:120'],
            'sections.family.*.relation' => ['nullable', 'string', 'max:80'],
            'sections.family.*.dependent' => ['nullable', 'boolean'],
            'sections.education' => ['sometimes', 'array', 'max:20'],
            'sections.education.*.qualification' => ['required_with:sections.education', 'string', 'max:160'],
            'sections.education.*.institute' => ['nullable', 'string', 'max:180'],
            'sections.education.*.year' => ['nullable', 'integer', 'min:1950', 'max:'.((int) now()->year + 1)],
            'sections.experience' => ['sometimes', 'array', 'max:30'],
            'sections.experience.*.company' => ['required_with:sections.experience', 'string', 'max:180'],
            'sections.experience.*.role' => ['nullable', 'string', 'max:160'],
            'sections.experience.*.from' => ['nullable', 'date'],
            'sections.experience.*.to' => ['nullable', 'date', 'after_or_equal:sections.experience.*.from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sections = $this->input('sections', []);

                if (! is_array($sections)) {
                    return;
                }

                $unsupported = array_diff(array_keys($sections), EmployeeProfileSection::SECTIONS);

                if ($unsupported !== []) {
                    $validator->errors()->add('sections', 'Unsupported profile section(s): '.implode(', ', $unsupported).'.');
                }
            },
        ];
    }
}
