<?php

namespace App\Http\Requests\Scoring;

use App\Domain\Scoring\Services\ScoringRuleCatalog;
use App\Models\ScoringRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreScoringRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ruleKey = (string) $this->input('rule_key');

        return $ruleKey !== ''
            && $this->user()?->can('createForKey', [ScoringRule::class, $ruleKey]) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rule_key' => ['required', 'string', Rule::in(array_keys(app(ScoringRuleCatalog::class)->labels()))],
            'name' => ['required', 'string', 'max:140'],
            'change_reason' => ['required', 'string', 'min:10', 'max:2000'],
            'effective_at' => ['nullable', 'date'],
        ];
    }
}
