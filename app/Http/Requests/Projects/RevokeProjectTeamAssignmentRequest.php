<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\ProjectTeamAssignment;
use Illuminate\Foundation\Http\FormRequest;

class RevokeProjectTeamAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        $assignment = $this->route('projectTeamAssignment');

        return $project instanceof Project
            && $assignment instanceof ProjectTeamAssignment
            && (int) $assignment->project_id === (int) $project->id
            && $this->user()?->can('delete', $assignment) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
