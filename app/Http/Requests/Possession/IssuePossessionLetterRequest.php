<?php

namespace App\Http\Requests\Possession;

use App\Models\PossessionHandover;
use Illuminate\Foundation\Http\FormRequest;

class IssuePossessionLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $possessionHandover = $this->route('possessionHandover');

        return $possessionHandover instanceof PossessionHandover
            && $this->user()?->can('issueLetter', $possessionHandover) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'possession_letter_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
