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
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('workTask');
                $assignee = \App\Models\User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                if ($task instanceof WorkTask && $assignee && $assignee->company_id !== $task->company_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The assignee must belong to the task company.');
                }
            },
        ];
    }
}
