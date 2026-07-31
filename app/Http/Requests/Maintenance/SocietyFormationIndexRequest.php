<?php

namespace App\Http\Requests\Maintenance;

use App\Models\SocietyFormation;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SocietyFormationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SocietyFormation::class) === true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'status' => ['nullable', 'string', Rule::in(['draft', 'application_filed', 'in_progress', 'formed', 'handed_over', 'blocked'])],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator): mixed => app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['project_id', 'status', 'per_page', 'page']),
        ];
    }
}
