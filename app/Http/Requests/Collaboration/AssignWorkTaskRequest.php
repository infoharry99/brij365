<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('assign', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_to_user_ids' => ['nullable', 'array'],
            'assigned_to_user_ids.*' => ['integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('workTask');
                $userIds = array_filter(array_map('intval', (array) ($this->input('assigned_to_user_ids') ?? [$this->input('assigned_to_user_id')])));

                if (empty($userIds)) {
                    $validator->errors()->add('assigned_to_user_id', 'At least one assignee is required.');
                    return;
                }

                $invalidAssignees = \App\Models\User::query()
                    ->whereIn('id', $userIds)
                    ->get()
                    ->filter(fn ($u) => $task instanceof WorkTask && $u->company_id && $task->company_id && $u->company_id !== $task->company_id);

                if ($invalidAssignees->isNotEmpty()) {
                    $validator->errors()->add('assigned_to_user_id', 'All assignees must belong to the task company.');
                }
            },
        ];
    }
}
