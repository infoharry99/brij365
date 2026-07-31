<?php

namespace App\Http\Requests\Maintenance;

use App\Models\CommonAreaHandoverItem;
use Illuminate\Foundation\Http\FormRequest;

class SignOffCommonAreaHandoverItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('commonAreaHandoverItem');

        return $item instanceof CommonAreaHandoverItem
            && $this->user()?->can('signOff', $item) === true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
