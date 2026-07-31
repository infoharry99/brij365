<?php

namespace App\Http\Requests\Procurement;

use App\Models\Project;
use App\Models\StockItem;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.manage') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source_stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')],
            'destination_project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'destination_store_type' => ['required', 'string', Rule::in(['central', 'site'])],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'min:0.001', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'transfer_reference' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $source = StockItem::query()->whereKey($this->integer('source_stock_item_id'))->first();
                $destinationProject = Project::query()->whereKey($this->integer('destination_project_id'))->first();
                $companyScope = app(CompanyScopeService::class);

                if (! $source || ! $user || ! $companyScope->allows($user, $source->company_id)) {
                    $validator->errors()->add('source_stock_item_id', 'The selected source stock item is not available for your company.');

                    return;
                }

                if ($source->status !== 'active') {
                    $validator->errors()->add('source_stock_item_id', 'Only active stock items can be transferred.');
                }

                if ((float) $this->input('quantity', 0) > (float) $source->on_hand_quantity) {
                    $validator->errors()->add('quantity', 'Transfer quantity cannot exceed available stock.');
                }

                if (! $destinationProject || ! $companyScope->allows($user, $destinationProject->company_id)) {
                    $validator->errors()->add('destination_project_id', 'The selected destination project is not available for your company.');

                    return;
                }

                if ((int) $destinationProject->company_id !== (int) $source->company_id) {
                    $validator->errors()->add('destination_project_id', 'Stock transfers must remain within the same company.');
                }

                if ($destinationProject->status !== 'active') {
                    $validator->errors()->add('destination_project_id', 'The selected destination project is not active.');
                }

                if (
                    (int) $source->project_id === (int) $this->integer('destination_project_id')
                    && $source->store_type === (string) $this->input('destination_store_type')
                ) {
                    $validator->errors()->add('destination_project_id', 'Destination project and store must differ from the source stock location.');
                }
            },
        ];
    }
}
