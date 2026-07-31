<?php

namespace App\Http\Requests\Collaboration;

use App\Models\User;
use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWorkTaskSubtaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'completed', 'cancelled'])],
            'priority' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'due_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('workTask');
                $assigneeId = $this->input('assigned_to_user_id');

                if (! $task instanceof WorkTask || ! $assigneeId) {
                    return;
                }

                $assigneeCompanyId = User::query()->whereKey($assigneeId)->value('company_id');

                if ((int) $assigneeCompanyId !== (int) $task->company_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The subtask assignee must belong to the task company.');
                }
            },
        ];
    }
}
