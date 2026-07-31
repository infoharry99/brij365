<?php

namespace App\Http\Requests\Scoring;

use App\Models\ScoreSnapshot;
use Illuminate\Foundation\Http\FormRequest;

final class OverrideScoreSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        $snapshot = $this->route('scoreSnapshot');
        if (! $snapshot instanceof ScoreSnapshot) {
            return false;
        }

        $ruleKey = $snapshot->relationLoaded('scoringRule')
            ? $snapshot->scoringRule?->rule_key
            : $snapshot->scoringRule()->value('rule_key');

        // This explicit boundary intentionally runs before the global wildcard
        // Gate hook: even a technical administrator must use the review-scoped
        // maker-checker workflow for an employee-performance override.
        if ($ruleKey === 'employee_performance') {
            return false;
        }

        return $this->user()?->can('override', $snapshot) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric', 'between:0,100'],
            'reason' => ['required', 'string', 'min:12', 'max:2000'],
        ];
    }
}
