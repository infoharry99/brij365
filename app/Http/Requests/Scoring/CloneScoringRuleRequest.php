<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Foundation\Http\FormRequest;

final class CloneScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clone', $this->route('scoringRule')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['change_reason' => ['required', 'string', 'min:12', 'max:2000']];
    }
}
