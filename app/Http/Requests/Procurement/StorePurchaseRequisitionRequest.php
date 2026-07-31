<?php

namespace App\Http\Requests\Procurement;

use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseRequisition::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'department' => ['required', 'string', 'max:120'],
            'required_by' => ['required', 'date', 'after_or_equal:today'],
            'priority' => ['required', 'string', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_code' => ['required', 'string', 'max:80'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:40'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'items.*.estimated_rate' => ['required', 'numeric', 'min:0', app(OperationalInputPolicy::class)->rateMaxRule()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = Project::query()->whereKey($this->integer('project_id'))->first();
                $user = $this->user();

                if (
                    ! $project
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $project->company_id)
                    || $project->status !== 'active'
                ) {
                    $validator->errors()->add('project_id', 'The selected project is not active for your company.');
                }
            },
        ];
    }
}