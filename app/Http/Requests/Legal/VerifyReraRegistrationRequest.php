<?php

namespace App\Http\Requests\Legal;

use App\Models\ReraRegistration;
use Illuminate\Foundation\Http\FormRequest;

class VerifyReraRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reraRegistration = $this->route('reraRegistration');

        return $reraRegistration instanceof ReraRegistration
            && ($this->user()?->can('verify', $reraRegistration) ?? false);
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
