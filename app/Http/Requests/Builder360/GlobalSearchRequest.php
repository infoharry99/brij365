<?php

namespace App\Http\Requests\Builder360;

use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

final class GlobalSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('use-global-search');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['q' => ['required', 'string', 'min:2', 'max:100']];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['q']);
            },
        ];
    }
}
