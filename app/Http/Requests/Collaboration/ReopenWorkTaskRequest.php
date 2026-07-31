<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class ReopenWorkTaskRequest extends FormRequest
{
    public function authorize(): bool { $task = $this->route('workTask'); return $task instanceof WorkTask && ($this->user()?->can('reopen', $task) ?? false); }
    public function rules(): array { return ['note' => ['required', 'string', 'max:1000']]; }
}
