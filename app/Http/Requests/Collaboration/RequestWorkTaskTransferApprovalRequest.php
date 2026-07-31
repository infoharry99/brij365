<?php

namespace App\Http\Requests\Collaboration;

use App\Models\User;
use App\Models\WorkTask;
use App\Models\WorkTaskTransferRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RequestWorkTaskTransferApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('requestTransfer', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $task = $this->route('workTask');
                $assignee = User::query()->whereKey($this->integer('assigned_to_user_id'))->first();

                if (! $task instanceof WorkTask || ! $assignee) {
                    return;
                }

                if ($assignee->company_id !== $task->company_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The proposed assignee must belong to the task company.');
                }

                if ((int) $assignee->id === (int) $task->assigned_to_user_id) {
                    $validator->errors()->add('assigned_to_user_id', 'The proposed assignee is already the current owner.');
                }

                $pendingExists = WorkTaskTransferRequest::query()
                    ->where('work_task_id', $task->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($pendingExists) {
                    $validator->errors()->add('task', 'A transfer approval request is already pending for this task.');
                }
            },
        ];
    }
}
