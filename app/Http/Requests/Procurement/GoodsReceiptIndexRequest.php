<?php

namespace App\Http\Requests\Procurement;

use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GoodsReceiptIndexRequest extends FormRequest
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
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
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
                    ['project_id', 'purchase_order_id', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('purchase_order_id')) {
                    $purchaseOrderCompanyId = PurchaseOrder::query()->whereKey($this->integer('purchase_order_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $purchaseOrderCompanyId)) {
                        $validator->errors()->add('purchase_order_id', 'The selected purchase order is not available for your company.');
                    }
                }
            },
        ];
    }
}
