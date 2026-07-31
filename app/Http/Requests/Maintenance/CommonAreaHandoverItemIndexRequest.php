<?php

namespace App\Http\Requests\Maintenance;

use App\Models\CommonAreaHandoverItem;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CommonAreaHandoverItemIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CommonAreaHandoverItem::class) === true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'society_formation_id' => ['nullable', 'integer', Rule::exists('society_formations', 'id')],
            'status' => ['nullable', 'string', Rule::in(['pending', 'in_progress', 'pending_snags', 'complete'])],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator): mixed => app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['project_id', 'society_formation_id', 'status', 'per_page', 'page']),
        ];
    }
}
