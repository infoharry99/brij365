<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Foundation\Http\FormRequest;

final class RetireScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('retire', $this->route('scoringRule')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:12', 'max:2000']];
    }
}
