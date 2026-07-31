<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DisposeLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();
        $lead = $this->route('lead');

        return $lead instanceof Lead
            && $user?->can('dispose', $lead) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['lost', 'deferred', 'duplicate', 'invalid', 'not_interested'])],
            'reason' => ['required', 'string', 'max:160'],
            'competitor_name' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
            'follow_up_at' => ['nullable', 'date', 'after:now', 'required_if:outcome,deferred'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lead = $this->route('lead');

            if (! $lead instanceof Lead) {
                return;
            }

            if (in_array($lead->status, ['won', 'lost'], true)) {
                $validator->errors()->add('lead', 'Closed leads cannot be dispositioned again.');
            }

            if ($lead->booking()->exists()) {
                $validator->errors()->add('lead', 'Booked leads must be managed from the booking workflow.');
            }
        });
    }
}
