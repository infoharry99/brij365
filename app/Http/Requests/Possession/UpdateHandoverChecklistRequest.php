<?php

namespace App\Http\Requests\Possession;

use App\Models\PossessionHandover;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHandoverChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $possessionHandover = $this->route('possessionHandover');

        return $possessionHandover instanceof PossessionHandover
            && $this->user()?->can('update', $possessionHandover) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checklist' => ['required', 'array', 'min:1', 'max:50'],
            'checklist.*.code' => ['required', 'string', 'max:80'],
            'checklist.*.label' => ['required', 'string', 'max:255'],
            'checklist.*.required' => ['required', 'boolean'],
            'checklist.*.completed' => ['required', 'boolean'],
        ];
    }
}
