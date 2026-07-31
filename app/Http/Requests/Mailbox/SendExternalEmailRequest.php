<?php

namespace App\Http\Requests\Mailbox;

use Illuminate\Foundation\Http\FormRequest;

class SendExternalEmailRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('send', $this->route('mailboxAccount')) === true; }
    public function rules(): array
    {
        $max = (int) config('mailbox.attachment_max_kb', 25600);
        return [
            'client_token' => ['required', 'uuid'],
            'to' => ['required', 'array', 'min:1', 'max:50'], 'to.*' => ['required', 'email:rfc'],
            'cc' => ['nullable', 'array', 'max:50'], 'cc.*' => ['email:rfc'], 'bcc' => ['nullable', 'array', 'max:50'], 'bcc.*' => ['email:rfc'],
            'subject' => ['required', 'string', 'max:255'], 'body' => ['nullable', 'string', 'max:200000'], 'body_html' => ['nullable', 'string', 'max:400000'],
            'in_reply_to' => ['nullable', 'string', 'max:998'], 'references' => ['nullable', 'string', 'max:8000'],
            'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:'.$max, 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,csv,txt,zip'],
            'inline_images' => ['nullable', 'array', 'max:10'], 'inline_images.*' => ['image', 'max:'.$max, 'mimes:jpg,jpeg,png,gif'],
            'remove_attachment_ids'=>['nullable','array','max:10'],'remove_attachment_ids.*'=>['integer','min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $seen = [];
        foreach (['to', 'cc', 'bcc'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = preg_split('/[,;\s]+/', $value) ?: [];
            }
            $normalized = collect((array) $value)->map(fn ($item) => strtolower(trim($item)))->filter()->reject(function ($item) use (&$seen): bool { if (isset($seen[$item])) return true; $seen[$item]=true; return false; })->values()->all();
            $this->merge([$field => $normalized]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (trim(strip_tags((string) $this->input('body_html'))) === '' && trim((string) $this->input('body')) === '') {
                $validator->errors()->add('body', 'Enter a message before sending this email.');
            }
        });
    }
}
