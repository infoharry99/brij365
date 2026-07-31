<?php

namespace App\Http\Requests\Maintenance;

use App\Models\CommonAreaHandoverItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCommonAreaHandoverItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('commonAreaHandoverItem');

        return $item instanceof CommonAreaHandoverItem
            && $this->user()?->can('update', $item) === true;
    }

    public function rules(): array
    {
        return [
            'checklist_completed' => ['required', 'integer', 'min:0'],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'pending_snags', 'complete'])],
            'snag_summary' => ['nullable', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $item = $this->route('commonAreaHandoverItem');

                if ($item instanceof CommonAreaHandoverItem && (int) $this->input('checklist_completed') > (int) $item->checklist_total) {
                    $validator->errors()->add('checklist_completed', 'Completed checklist count cannot exceed total checklist count.');
                }
            },
        ];
    }
}
