<?php

namespace App\Http\Requests\Legal;

use App\Models\ProjectApproval;
use Illuminate\Foundation\Http\FormRequest;

class VerifyProjectApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $projectApproval = $this->route('projectApproval');

        return $projectApproval instanceof ProjectApproval
            && ($this->user()?->can('verify', $projectApproval) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'verification_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
