<?php

namespace App\Http\Requests\Settings;

use App\Models\Company;
use App\Models\DataImportBatch;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PreviewDataImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DataImportBatch::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'import_type' => ['required', 'string', Rule::in([
                DataImportBatch::TYPE_CRM_PROSPECT_INQUIRIES,
                DataImportBatch::TYPE_HR_EMPLOYEES,
            ])],
            'source_file' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($user->hasPermission('*')) {
                    if (! $this->filled('company_id')) {
                        $validator->errors()->add('company_id', 'Global users must select a company for data imports.');

                        return;
                    }
                } elseif ($user->company_id === null) {
                    $validator->errors()->add('company_id', 'A company assignment is required before previewing imports.');

                    return;
                }

                $companyId = $this->filled('company_id')
                    ? Company::query()->whereKey($this->integer('company_id'))->value('id')
                    : $user->company_id;

                if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                    $validator->errors()->add('company_id', 'The selected company is not available for your user scope.');
                }
            },
        ];
    }
}
