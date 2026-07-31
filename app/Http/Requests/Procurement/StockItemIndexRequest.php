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

class StockItemIndexRequest extends FormRequest
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
            'store_type' => ['nullable', 'string', Rule::in(['central', 'site'])],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'item_code' => ['nullable', 'string', 'max:80'],
            'low_stock' => ['nullable', 'boolean'],
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
                    ['project_id', 'store_type', 'status', 'item_code', 'low_stock', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty() || ! $this->filled('project_id')) {
                    return;
                }

                $projectCompanyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                    $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                }
            },
        ];
    }
}
