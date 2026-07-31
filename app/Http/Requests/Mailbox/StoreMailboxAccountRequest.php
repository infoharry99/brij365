<?php

namespace App\Http\Requests\Mailbox;

use App\Models\MailboxAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailboxAccountRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', MailboxAccount::class) === true; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:255', Rule::unique('mailbox_accounts')->where('company_id', $this->user()->company_id)],
            'imap_host' => ['required', 'string', 'max:255'], 'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls'])], 'imap_validate_cert' => ['nullable', 'boolean'],
            'smtp_host' => ['required', 'string', 'max:255'], 'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['nullable', Rule::in(['ssl', 'tls'])], 'username' => ['required', 'string', 'max:255'],
            'secret' => ['required', 'string', 'max:4096'], 'sync_interval_minutes' => ['nullable', 'integer', 'between:1,1440'],
            'signature' => ['nullable','string','max:5000'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }
}
