<?php

namespace App\Http\Requests\Procurement;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProcurementDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PurchaseOrder::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
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
                    ['project_id', 'vendor_id', 'date_from', 'date_to'],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                    if (! $companyScope->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('vendor_id')) {
                    $vendorCompanyId = Vendor::query()->whereKey($this->integer('vendor_id'))->value('company_id');

                    if (! $companyScope->allows($user, $vendorCompanyId)) {
                        $validator->errors()->add('vendor_id', 'The selected vendor is not available for your company.');
                    }
                }
            },
        ];
    }
}
