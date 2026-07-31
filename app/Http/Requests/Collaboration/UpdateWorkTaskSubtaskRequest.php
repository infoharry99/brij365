<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use App\Models\WorkTaskSubtask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkTaskSubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('manageSubtasks', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('workTask');
                $subtask = $this->route('workTaskSubtask');

                if ($task instanceof WorkTask && $subtask instanceof WorkTaskSubtask && (int) $subtask->work_task_id !== (int) $task->id) {
                    $validator->errors()->add('subtask', 'The selected subtask does not belong to this task.');
                }
            },
        ];
    }
}
