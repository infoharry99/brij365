<?php

namespace App\Http\Requests\Hr;

use App\Models\LeaveProcessingRun;
use Illuminate\Foundation\Http\FormRequest;

class PostLeaveProcessingRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        $run = $this->route('leaveProcessingRun');

        return $run instanceof LeaveProcessingRun
            && $this->user()?->can('post', $run) === true;
    }

    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
