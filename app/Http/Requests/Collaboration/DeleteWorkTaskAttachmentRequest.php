<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use App\Models\WorkTaskAttachment;
use Illuminate\Foundation\Http\FormRequest;

class DeleteWorkTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');
        $attachment = $this->route('workTaskAttachment');

        return $task instanceof WorkTask
            && $attachment instanceof WorkTaskAttachment
            && (int) $attachment->work_task_id === (int) $task->id
            && ($this->user()?->can('updateDetails', $task) ?? false);
    }

    public function rules(): array
    {
        return [];
    }
}
