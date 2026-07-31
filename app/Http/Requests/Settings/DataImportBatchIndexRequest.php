<?php

namespace App\Http\Requests\Settings;

use App\Models\Company;
use App\Models\DataImportBatch;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DataImportBatchIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DataImportBatch::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'import_type' => ['nullable', 'string', Rule::in([
                DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
                DataImportBatch::TYPE_HR_EMPLOYEES,
            ])],
            'status' => ['nullable', 'string', Rule::in([
                DataImportBatch::STATUS_PREVIEW,
                DataImportBatch::STATUS_POSTED,
                DataImportBatch::STATUS_FAILED,
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['company_id', 'import_type', 'status', 'page', 'per_page'],
                );

                if (! $this->filled('company_id')) {
                    return;
                }

                $companyId = Company::query()->whereKey($this->integer('company_id'))->value('id');

                if (! app(CompanyScopeService::class)->allows($this->user(), $companyId)) {
                    $validator->errors()->add('company_id', 'The selected company is not available for your user scope.');
                }
            },
        ];
    }
}
