<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use App\Domain\Collaboration\TaskLifecycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('updateStatus', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::in(array_keys(TaskLifecycle::statuses()))],
            'note' => ['required', 'string', 'max:1000'],
        ];
    }
}
