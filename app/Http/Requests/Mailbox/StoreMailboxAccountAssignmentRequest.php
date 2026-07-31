<?php

namespace App\Http\Requests\Mailbox;

use App\Models\MailboxAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailboxAccountAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('mailboxAccount')) === true;
    }

    public function rules(): array
    {
        /** @var MailboxAccount $account */
        $account = $this->route('mailboxAccount');

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('company_id', $account->company_id)->where('status', 'active')],
            'can_view' => ['nullable', 'boolean'],
            'can_send' => ['nullable', 'boolean'],
            'can_manage' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
