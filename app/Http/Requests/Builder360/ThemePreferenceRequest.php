<?php

namespace App\Http\Requests\Builder360;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ThemePreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('change-theme');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['theme' => ['required', 'string', Rule::in(['light', 'dark'])]];
    }
}
