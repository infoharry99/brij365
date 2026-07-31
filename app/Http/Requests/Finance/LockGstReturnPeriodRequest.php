<?php

namespace App\Http\Requests\Finance;

use App\Models\GstReturnPeriod;
use Illuminate\Foundation\Http\FormRequest;

class LockGstReturnPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $period = $this->route('gstReturnPeriod');

        return $period instanceof GstReturnPeriod
            && $this->user()?->can('lock', $period) === true;
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
