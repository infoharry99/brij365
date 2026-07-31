<?php

namespace App\Http\Requests\Inventory;

use App\Models\UnitPriceVersion;
use Illuminate\Foundation\Http\FormRequest;

class ApproveUnitPriceVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var UnitPriceVersion|null $unitPriceVersion */
        $unitPriceVersion = $this->route('unitPriceVersion');

        return $unitPriceVersion instanceof UnitPriceVersion
            && $this->user()?->can('approve', $unitPriceVersion) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
