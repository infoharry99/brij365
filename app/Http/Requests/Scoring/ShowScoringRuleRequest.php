<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Foundation\Http\FormRequest;

final class ShowScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('scoringRule')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['compare_to' => ['nullable', 'integer', 'min:1', 'exists:scoring_rules,id']];
    }
}
