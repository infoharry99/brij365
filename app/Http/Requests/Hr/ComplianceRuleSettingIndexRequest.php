<?php

namespace App\Http\Requests\Hr;

use App\Models\SystemSetting;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ComplianceRuleSettingIndexRequest extends FormRequest
{
    public const ALLOWED_SETTING_KEYS = [
        'payroll.tax_rules',
        'finance.gst_rules',
        'hr.statutory.pf',
        'hr.statutory.esic',
        'hr.statutory.professional_tax',
        'hr.statutory.labour_welfare_fund',
        'hr.statutory.gratuity_bonus',
        'hr.leave.rules',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasPermission('compliance.view') === true
            || $user?->hasPermission('compliance.manage') === true
            || $user?->can('viewAny', SystemSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'setting_key' => ['nullable', 'string', Rule::in(self::ALLOWED_SETTING_KEYS)],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'archived'])],
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['setting_key', 'status', 'company_id', 'per_page', 'page'],
                );

                $user = $this->user();
                if (! $user) {
                    return;
                }

                if ($this->filled('company_id') && ! app(CompanyScopeService::class)->allowsSettingRead($user, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'You can view compliance rules only for your active company scope.');
                }
            },
        ];
    }
}
