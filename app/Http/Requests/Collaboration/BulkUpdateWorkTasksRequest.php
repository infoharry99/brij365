<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateWorkTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WorkTask::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1', 'max:50'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['open', 'in_progress', 'blocked', 'completed', 'cancelled'])],
            'priority' => ['sometimes', 'required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->exists('status') && ! $this->exists('priority')) {
                    $validator->errors()->add('task', 'At least one bulk update field is required.');

                    return;
                }

                $taskIds = collect($this->input('task_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                $tasks = WorkTask::query()
                    ->whereIn('id', $taskIds->all())
                    ->get()
                    ->keyBy('id');

                if ($tasks->count() !== $taskIds->count()) {
                    $validator->errors()->add('task_ids', 'All selected tasks must exist and be active.');

                    return;
                }

                $unauthorized = $tasks->contains(
                    fn (WorkTask $task): bool => ! ($this->user()?->can('updateStatus', $task) ?? false),
                );

                if ($unauthorized) {
                    $validator->errors()->add('task_ids', 'You are not allowed to update one or more selected tasks.');
                }
            },
        ];
    }
}
