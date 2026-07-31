<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTaskCompletionApproval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideWorkTaskCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $approval = $this->route('workTaskCompletionApproval');
        return $approval instanceof WorkTaskCompletionApproval && ($this->user()?->can('approveCompletion', $approval->task) ?? false);
    }
    public function rules(): array { return ['decision' => ['required', Rule::in(['approve', 'reject'])], 'note' => ['required', 'string', 'max:1000']]; }
}
