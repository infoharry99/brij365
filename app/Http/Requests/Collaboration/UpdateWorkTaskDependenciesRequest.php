<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWorkTaskDependenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('updateDetails', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'dependency_task_ids' => ['present', 'array', 'max:20'],
            'dependency_task_ids.*' => ['integer', 'distinct', 'exists:work_tasks,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                    return;
                }

                $task = $this->route('workTask');

                if (! $task instanceof WorkTask) {
                    return;
                }

                $dependencyIds = collect($this->input('dependency_task_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($dependencyIds->contains((int) $task->id)) {
                    $validator->errors()->add('dependency_task_ids', 'A task cannot depend on itself.');

                    return;
                }

                if ($dependencyIds->isEmpty()) {
                    return;
                }

                $dependencies = WorkTask::query()
                    ->whereIn('id', $dependencyIds->all())
                    ->get(['id', 'company_id', 'metadata']);

                if ($dependencies->count() !== $dependencyIds->count()) {
                    $validator->errors()->add('dependency_task_ids', 'One or more selected dependencies are not available.');

                    return;
                }

                foreach ($dependencies as $dependency) {
                    if (! app(CompanyScopeService::class)->allows($this->user(), $dependency->company_id) || (int) $dependency->company_id !== (int) $task->company_id) {
                        $validator->errors()->add('dependency_task_ids', 'Dependencies must belong to the same company and user scope as the task.');

                        return;
                    }

                    $dependencyMetadata = $dependency->metadata ?? [];
                    $nestedIds = collect($dependencyMetadata['dependency_task_ids'] ?? [])
                        ->map(fn ($id): int => (int) $id)
                        ->filter()
                        ->values();

                    if ($nestedIds->contains((int) $task->id)) {
                        $validator->errors()->add('dependency_task_ids', 'This dependency would create a circular task relationship.');

                        return;
                    }
                }
            },
        ];
    }
}
