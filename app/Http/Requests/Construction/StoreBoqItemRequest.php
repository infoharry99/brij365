<?php

namespace App\Http\Requests\Construction;

use App\Models\BoqItem;
use App\Models\ConstructionMilestone;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBoqItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BoqItem::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'construction_milestone_id' => ['nullable', 'integer', Rule::exists('construction_milestones', 'id')],
            'vendor_id' => ['nullable', 'integer', Rule::exists('vendors', 'id')],
            'boq_code' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9\\-\\.]+$/'],
            'trade' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:40'],
            'planned_quantity' => ['required', 'numeric', 'min:0.001', app(OperationalInputPolicy::class)->constructionQuantityMaxRule()],
            'rate' => ['required', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->rateMaxRule()],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'closed'])],
            'specifications' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = $this->user();
                $project = Project::query()->whereKey($this->integer('project_id'))->first();

                if (
                    ! $project
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $project->company_id)
                    || $project->status !== 'active'
                ) {
                    $validator->errors()->add('project_id', 'The selected project is not active for your company.');

                    return;
                }

                if ($this->integer('construction_milestone_id') > 0) {
                    $milestoneValid = ConstructionMilestone::query()
                        ->whereKey($this->integer('construction_milestone_id'))
                        ->where('company_id', $project->company_id)
                        ->where('project_id', $project->id)
                        ->exists();

                    if (! $milestoneValid) {
                        $validator->errors()->add('construction_milestone_id', 'The milestone must belong to the selected project.');
                    }
                }

                if ($this->integer('vendor_id') > 0) {
                    $vendorValid = Vendor::query()
                        ->whereKey($this->integer('vendor_id'))
                        ->where('company_id', $project->company_id)
                        ->where('status', 'active')
                        ->exists();

                    if (! $vendorValid) {
                        $validator->errors()->add('vendor_id', 'The vendor must be active for your company.');
                    }
                }

                $exists = BoqItem::query()
                    ->where('project_id', $project->id)
                    ->where('boq_code', strtoupper((string) $this->input('boq_code')))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('boq_code', 'This BOQ code already exists for the selected project.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('boq_code')) {
            $this->merge(['boq_code' => strtoupper((string) $this->input('boq_code'))]);
        }
    }
}