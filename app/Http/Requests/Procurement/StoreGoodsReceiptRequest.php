<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseOrder;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = PurchaseOrder::query()->whereKey($this->integer('purchase_order_id'))->first();

        return $purchaseOrder !== null && $this->user()?->can('receive', $purchaseOrder) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', Rule::exists('purchase_orders', 'id')],
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'delivery_challan_number' => ['nullable', 'string', 'max:120'],
            'quality_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_code' => ['required', 'string', 'max:80'],
            'items.*.accepted_quantity' => ['required', 'numeric', 'min:0.01', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $purchaseOrder = PurchaseOrder::query()->whereKey($this->integer('purchase_order_id'))->first();
                $user = $this->user();

                if (
                    ! $purchaseOrder
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $purchaseOrder->company_id)
                ) {
                    $validator->errors()->add('purchase_order_id', 'The selected purchase order is not available for your company.');

                    return;
                }

                if (! in_array($purchaseOrder->status, ['approved', 'partially_received'], true)) {
                    $validator->errors()->add('purchase_order_id', 'Goods can be received only against approved purchase orders.');
                }

                $itemCodes = collect($this->input('items', []))
                    ->pluck('item_code')
                    ->filter()
                    ->map(fn ($itemCode): string => strtoupper((string) $itemCode));

                if ($itemCodes->count() !== $itemCodes->unique()->count()) {
                    $validator->errors()->add('items', 'Duplicate goods receipt item codes are not allowed.');
                }
            },
        ];
    }
}
