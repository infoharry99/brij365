<?php

namespace App\Http\Requests\Recruitment;

use App\Models\JobOffer;
use Illuminate\Foundation\Http\FormRequest;

class ReleaseJobOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jobOffer = $this->route('jobOffer');

        return $jobOffer instanceof JobOffer
            && ($this->user()?->can('release', $jobOffer) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'release_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
