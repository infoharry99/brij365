<?php

namespace App\Http\Requests\Possession;

use App\Models\PossessionHandover;
use Illuminate\Foundation\Http\FormRequest;

class CompletePossessionHandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        $possessionHandover = $this->route('possessionHandover');

        return $possessionHandover instanceof PossessionHandover
            && $this->user()?->can('complete', $possessionHandover) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actual_handover_on' => ['required', 'date', 'before_or_equal:today'],
            'possession_letter_reference' => ['required', 'string', 'max:255'],
        ];
    }
}
