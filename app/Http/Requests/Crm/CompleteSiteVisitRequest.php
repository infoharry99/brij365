<?php

namespace App\Http\Requests\Crm;

use App\Models\SiteVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $siteVisit = $this->route('siteVisit');

        return $siteVisit instanceof SiteVisit && ($this->user()?->can('complete', $siteVisit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', Rule::in(['interested', 'follow_up_required', 'booking_expected', 'not_interested', 'no_show'])],
            'outcome_notes' => ['required', 'string', 'max:5000'],
            'next_follow_up_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
