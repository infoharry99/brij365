<?php

namespace App\Http\Requests\Builder360;

use App\Support\PaginationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApprovalCenterIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['tab', 'module', 'priority', 'status', 'project_id'] as $key) {
            $value = $this->input($key);
            if ($value === '' || $value === 'all' || $value === 'All') {
                $normalized[$key] = null;
            }
        }

        if ($this->filled('tab')) {
            $normalized['tab'] = str($this->input('tab'))->lower()->replace([' ', '-'], '_')->toString();
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', Rule::in(['pending', 'high_priority', 'actionable', 'restricted', 'approved'])],
            'q' => ['nullable', 'string', 'max:120'],
            'module' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'string', Rule::in(['high', 'med', 'low'])],
            'status' => ['nullable', 'string', 'max:60'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
