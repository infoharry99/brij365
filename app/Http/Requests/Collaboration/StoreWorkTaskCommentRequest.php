<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('comment', $task) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'mentions' => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer', 'exists:users,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
