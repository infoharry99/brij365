<?php

namespace App\Http\Requests\Construction;

use App\Models\BoqItem;
use App\Models\ContractorMeasurement;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContractorMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ContractorMeasurement::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')],
            'measurement_date' => ['required', 'date', 'before_or_equal:today'],
            'bill_reference' => ['nullable', 'string', 'max:80'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.boq_item_id' => ['required', 'integer', Rule::exists('boq_items', 'id')],
            'lines.*.measured_quantity' => ['required', 'numeric', 'min:0.001', app(OperationalInputPolicy::class)->constructionQuantityMaxRule()],
            'lines.*.certified_quantity' => ['nullable', 'numeric', 'min:0', app(OperationalInputPolicy::class)->constructionQuantityMaxRule()],
            'lines.*.remarks' => ['nullable', 'string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:3000'],
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

                $vendorValid = Vendor::query()
                    ->whereKey($this->integer('vendor_id'))
                    ->where('company_id', $project->company_id)
                    ->where('status', 'active')
                    ->exists();

                if (! $vendorValid) {
                    $validator->errors()->add('vendor_id', 'The vendor must be active for your company.');
                }

                $boqIds = collect($this->input('lines', []))->pluck('boq_item_id')->map(fn ($id): int => (int) $id);
                if ($boqIds->count() !== $boqIds->unique()->count()) {
                    $validator->errors()->add('lines', 'Duplicate BOQ items are not allowed in one measurement.');
                }

                $validBoqCount = BoqItem::query()
                    ->where('company_id', $project->company_id)
                    ->where('project_id', $project->id)
                    ->where('status', 'active')
                    ->whereIn('id', $boqIds->unique()->values())
                    ->count();

                if ($boqIds->isNotEmpty() && $validBoqCount !== $boqIds->unique()->count()) {
                    $validator->errors()->add('lines', 'All BOQ lines must be active and belong to the selected project.');
                }

                foreach ($this->input('lines', []) as $index => $line) {
                    $measured = (float) ($line['measured_quantity'] ?? 0);
                    $certified = array_key_exists('certified_quantity', $line) ? (float) $line['certified_quantity'] : $measured;

                    if ($certified > $measured) {
                        $validator->errors()->add("lines.$index.certified_quantity", 'Certified quantity cannot exceed measured quantity.');
                    }
                }
            },
        ];
    }
}