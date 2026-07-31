<?php

namespace App\Http\Requests\Scoring;

use Illuminate\Foundation\Http\FormRequest;

final class RecalculateScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recalculate', $this->route('scoringRule')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array { return []; }
}
