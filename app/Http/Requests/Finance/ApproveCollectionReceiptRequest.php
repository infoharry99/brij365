<?php

namespace App\Http\Requests\Finance;

use App\Models\CollectionReceipt;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCollectionReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('collectionReceipt');

        return $receipt instanceof CollectionReceipt
            && $this->user()?->can('approve', $receipt) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
