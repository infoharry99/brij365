<?php

namespace App\Http\Requests\Procurement;

use App\Models\StockItem;
use App\Services\Security\CompanyScopeService;
use App\Support\OperationalInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockIssueRequest extends FormRequest
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
            'stock_item_id' => ['required', 'integer', Rule::exists('stock_items', 'id')],
            'movement_type' => ['required', 'string', Rule::in(['issue', 'consumption', 'wastage'])],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'quantity' => ['required', 'numeric', 'min:0.001', app(OperationalInputPolicy::class)->procurementQuantityMaxRule()],
            'issue_reference' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $stockItem = StockItem::query()->whereKey($this->integer('stock_item_id'))->first();
                $user = $this->user();

                if (! $stockItem || ! $user || ! app(CompanyScopeService::class)->allows($user, $stockItem->company_id)) {
                    $validator->errors()->add('stock_item_id', 'The selected stock item is not available for your company.');

                    return;
                }

                if ($stockItem->status !== 'active') {
                    $validator->errors()->add('stock_item_id', 'Only active stock items can be issued.');
                }

                if ((float) $this->input('quantity', 0) > (float) $stockItem->on_hand_quantity) {
                    $validator->errors()->add('quantity', 'Issued quantity cannot exceed available stock.');
                }
            },
        ];
    }
}
