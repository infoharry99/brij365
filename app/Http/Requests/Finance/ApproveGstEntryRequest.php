<?php

namespace App\Http\Requests\Finance;

use App\Models\GstEntry;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGstEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('gstEntry');

        return $entry instanceof GstEntry
            && $this->user()?->can('approve', $entry) === true;
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
