<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class DuplicateWorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask
            && ($this->user()?->can('view', $task) ?? false)
            && ($this->user()?->can('create', WorkTask::class) ?? false);
    }

    public function rules(): array
    {
        return ['client_token' => ['required', 'uuid']];
    }
}
