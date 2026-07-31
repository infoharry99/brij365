<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTaskTransferRequest;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResolveWorkTaskTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transferRequest = $this->route('workTaskTransferRequest');
        $user = $this->user();

        if (! $transferRequest instanceof WorkTaskTransferRequest || ! $user) {
            return false;
        }

        return $user->hasPermission('collaboration.manage')
            && app(CompanyScopeService::class)->allows($user, $transferRequest->company_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $transferRequest = $this->route('workTaskTransferRequest');
                $user = $this->user();

                if (! $transferRequest instanceof WorkTaskTransferRequest || ! $user) {
                    return;
                }

                if ($transferRequest->status !== 'pending') {
                    $validator->errors()->add('transfer_request', 'Only pending transfer requests can be resolved.');
                }

                if ((int) $transferRequest->requested_by_user_id === (int) $user->id) {
                    $validator->errors()->add('transfer_request', 'The requester cannot approve or reject the same transfer request.');
                }
            },
        ];
    }
}
