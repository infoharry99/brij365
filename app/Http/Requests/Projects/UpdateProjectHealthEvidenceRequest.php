<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateProjectHealthEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('update', $project) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'construction_progress' => ['required', 'numeric', 'between:0,100'],
            'sales_progress' => ['required', 'numeric', 'between:0,100'],
            'collection_progress' => ['required', 'numeric', 'between:0,100'],
            'budget_control' => ['required', 'numeric', 'between:0,100'],
            'schedule_variance' => ['required', 'numeric', 'between:0,100'],
            'inventory_health' => ['required', 'numeric', 'between:0,100'],
            'approval_delays' => ['required', 'numeric', 'between:0,100'],
            'procurement_delays' => ['required', 'numeric', 'between:0,100'],
            'receivables' => ['required', 'numeric', 'between:0,100'],
        ];
    }
}
