<?php

namespace App\Http\Requests\Possession;

use App\Models\HandoverSnag;
use Illuminate\Foundation\Http\FormRequest;

class ResolveHandoverSnagRequest extends FormRequest
{
    public function authorize(): bool
    {
        $handoverSnag = $this->route('handoverSnag');

        return $handoverSnag instanceof HandoverSnag
            && $this->user()?->can('resolve', $handoverSnag) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ];
    }
}
