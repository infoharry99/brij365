<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Candidate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof Candidate
            && ($this->user()?->can('update', $candidate) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', 'string', Rule::in(['selected', 'rejected'])],
            'transition_note' => ['nullable', 'string', 'max:2000'],
            'return_to' => ['nullable', 'string', Rule::in(['candidates', 'pipeline'])],
        ];
    }
}
