<?php

namespace App\Http\Requests\Construction;

use App\Models\ContractorBill;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContractorBillIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ContractorBill::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'partially_paid', 'paid'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
                    ['project_id', 'vendor_id', 'status', 'date_from', 'date_to', 'per_page', 'page'],
                );

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateScopedProject($validator);
                $this->validateScopedVendor($validator);
            },
        ];
    }

    private function validateScopedProject(Validator $validator): void
    {
        $user = $this->user();

        if (! $user || ! $this->filled('project_id')) {
            return;
        }

        $projectCompanyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

        if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
            $validator->errors()->add('project_id', 'The selected project is not available for your company.');
        }
    }

    private function validateScopedVendor(Validator $validator): void
    {
        $user = $this->user();

        if (! $user || ! $this->filled('vendor_id')) {
            return;
        }

        $vendorCompanyId = Vendor::query()->whereKey($this->integer('vendor_id'))->value('company_id');

        if (! app(CompanyScopeService::class)->allows($user, $vendorCompanyId)) {
            $validator->errors()->add('vendor_id', 'The selected vendor is not available for your company.');
        }
    }
}
