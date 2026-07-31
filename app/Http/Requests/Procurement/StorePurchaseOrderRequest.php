<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_requisition_id' => ['nullable', 'integer', Rule::exists('purchase_requisitions', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')],
            'po_date' => ['required', 'date'],
            'expected_delivery_on' => ['nullable', 'date', 'after_or_equal:po_date'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_code' => ['required', 'string', 'max:80'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'items.*.rate' => ['required', 'numeric', 'min:0', app(OperationalInputPolicy::class)->rateMaxRule()],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $vendor = Vendor::query()->whereKey($this->integer('vendor_id'))->first();

                if (
                    ! $vendor
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $vendor->company_id)
                    || $vendor->status !== 'active'
                ) {
                    $validator->errors()->add('vendor_id', 'The selected vendor is not active for your company.');
                }

                if (! $this->filled('purchase_requisition_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if (
                        ! $project
                        || ! $user
                        || ! app(CompanyScopeService::class)->allows($user, $project->company_id)
                        || $project->status !== 'active'
                    ) {
                        $validator->errors()->add('project_id', 'Project is required and must be active for direct purchase orders.');
                    }

                    if ($project && $vendor && $project->company_id !== $vendor->company_id) {
                        $validator->errors()->add('vendor_id', 'Vendor and project must belong to the same company.');
                    }

                    return;
                }

                $requisition = PurchaseRequisition::query()->whereKey($this->integer('purchase_requisition_id'))->first();

                if (
                    ! $requisition
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $requisition->company_id)
                    || $requisition->status !== 'approved'
                ) {
                    $validator->errors()->add('purchase_requisition_id', 'Purchase orders can be created only from an approved requisition.');
                }

                if ($requisition && $vendor && $requisition->company_id !== $vendor->company_id) {
                    $validator->errors()->add('vendor_id', 'Vendor and requisition must belong to the same company.');
                }
            },
        ];
    }
}