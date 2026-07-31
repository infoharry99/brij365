<?php

namespace App\Http\Requests\Procurement;

use App\Models\Vendor;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VendorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Vendor::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'blocked'])],
            'vendor_type' => ['nullable', 'string', Rule::in(['material', 'contractor', 'service', 'consultant'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['status', 'vendor_type', 'search', 'per_page', 'page'],
                );
            },
        ];
    }
}
