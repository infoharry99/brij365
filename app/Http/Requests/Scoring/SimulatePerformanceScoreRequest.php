<?php

namespace App\Http\Requests\Scoring;

use App\Application\Scoring\DTOs\PerformanceScoreSimulationInputData;
use App\Domain\Scoring\Services\LogicCenterAccessService;
use App\Models\ScoringRule;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SimulatePerformanceScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $rule = $this->route('scoringRule');
        if ($user === null || ! $rule instanceof ScoringRule || $rule->rule_key !== 'employee_performance') {
            return false;
        }

        $access = app(LogicCenterAccessService::class);

        return $access->canViewSection($user, 'simulation')
            && $access->capabilities($user)['managePerformance']
            && app(CompanyScopeService::class)->allows($user, $rule->company_id);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'criterion_scores' => ['required', 'array', 'min:1', 'max:20'],
            'criterion_scores.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'performance_simulation_rule_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $rule = $this->route('scoringRule');
            if (! $rule instanceof ScoringRule) {
                return;
            }

            if ($this->filled('performance_simulation_rule_id')
                && (int) $this->input('performance_simulation_rule_id') !== (int) $rule->id) {
                $validator->errors()->add('performance_simulation_rule_id', 'The selected performance rule version does not match this simulation request.');
            }

            $criteria = collect((array) data_get($rule->configuration, 'criteria', []))
                ->filter(static fn (mixed $criterion): bool => is_array($criterion));
            $allowed = $criteria->pluck('key')->map(static fn (mixed $key): string => (string) $key)->filter();
            $submitted = collect((array) $this->input('criterion_scores', []));

            foreach ($submitted->keys()->diff($allowed) as $unknown) {
                $validator->errors()->add('criterion_scores.'.(string) $unknown, 'This criterion does not belong to the selected rule version.');
            }

            foreach ($criteria as $criterion) {
                $key = (string) ($criterion['key'] ?? '');
                $required = (bool) ($criterion['required'] ?? true)
                    || (string) ($criterion['missing_data_behavior'] ?? 'block') === 'block';
                if ($key !== '' && $required && (! $submitted->has($key) || $submitted->get($key) === null || $submitted->get($key) === '')) {
                    $validator->errors()->add('criterion_scores.'.$key, (string) ($criterion['label'] ?? $key).' is required for this rule version.');
                }
            }
        }];
    }

    public function simulationInput(): PerformanceScoreSimulationInputData
    {
        return new PerformanceScoreSimulationInputData(
            criterionScores: collect((array) $this->validated('criterion_scores'))
                ->reject(static fn (mixed $value): bool => $value === null || $value === '')
                ->map(static fn (mixed $value): float => (float) $value)
                ->all(),
        );
    }
}
