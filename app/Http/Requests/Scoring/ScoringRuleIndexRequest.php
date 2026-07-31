<?php

namespace App\Http\Requests\Scoring;

use App\Domain\Scoring\Services\LogicCenterAccessService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class ScoringRuleIndexRequest extends FormRequest
{
    public const VIEWS = [
        'overview', 'business', 'lead', 'performance', 'confirmation', 'recruitment', 'vendor', 'project',
        'customer-satisfaction', 'exit-feedback', 'score-history', 'rule-history',
        'statutory', 'roster', 'simulation', 'audit',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $view = (string) $this->query('view', 'overview');
        if (! in_array($view, self::VIEWS, true)) {
            return app(LogicCenterAccessService::class)->canViewAny($user);
        }

        $section = match ($view) {
            'lead', 'confirmation', 'recruitment', 'vendor', 'project', 'customer-satisfaction', 'exit-feedback' => 'business',
            'score-history', 'rule-history' => 'audit',
            default => $view,
        };

        return app(LogicCenterAccessService::class)->canViewSection($user, $section);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'view' => ['nullable', 'string', Rule::in(self::VIEWS)],
            'status' => ['nullable', 'string', Rule::in(['draft', 'validated', 'pending_approval', 'approved', 'scheduled', 'active', 'rejected', 'retired', 'superseded'])],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator) => app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['view', 'status', 'q'])];
    }
}
