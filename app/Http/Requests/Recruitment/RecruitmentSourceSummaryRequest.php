<?php

namespace App\Http\Requests\Recruitment;

use App\Models\Candidate;
use App\Models\Company;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecruitmentSourceSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Candidate::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'source' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['company_id', 'source', 'department', 'date_from', 'date_to'],
                );

                $actor = $this->user();

                if (! $actor) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);

                if ($this->filled('company_id') && ! $companyScope->allows($actor, $this->integer('company_id'))) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }

                if ($this->filled('company_id')) {
                    $company = Company::query()->whereKey($this->integer('company_id'))->first();

                    if ($company && $company->status !== 'active') {
                        $validator->errors()->add('company_id', 'The selected company is not active.');
                    }
                }
            },
        ];
    }
}
