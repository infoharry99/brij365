<?php

namespace App\Http\Requests\Procurement;

use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateVendorPerformanceEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor && $this->user()?->can('update', $vendor) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'acceptance_rate' => ['required', 'numeric', 'between:0,100'],
            'quality' => ['required', 'numeric', 'between:0,100'],
            'on_time_delivery' => ['required', 'numeric', 'between:0,100'],
            'fulfillment' => ['required', 'numeric', 'between:0,100'],
            'price_competitiveness' => ['required', 'numeric', 'between:0,100'],
            'documentation' => ['required', 'numeric', 'between:0,100'],
            'responsiveness' => ['required', 'numeric', 'between:0,100'],
            'issue_resolution' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
