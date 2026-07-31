<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveRequest;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LeaveTypeIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeaveRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), [
                'per_page',
                'page',
            ]);
        });
    }
}
