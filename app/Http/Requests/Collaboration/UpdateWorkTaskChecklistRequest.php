<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkTaskChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('updateChecklist', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'checklist' => ['nullable', 'array', 'max:100'],
            'checklist.*.label' => ['required_without:checklist.*.text', 'string', 'max:255'],
            'checklist.*.text' => ['required_without:checklist.*.label', 'string', 'max:255'],
            'checklist.*.done' => ['nullable', 'boolean'],
            'new_item' => ['nullable', 'string', 'max:255', 'required_without:checklist'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
