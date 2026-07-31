<?php

namespace App\Http\Requests\Builder360;

use Illuminate\Foundation\Http\FormRequest;

final class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-builder360-dashboard') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
