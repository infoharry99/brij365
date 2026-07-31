<?php

namespace App\Http\Requests\Crm;

use App\Models\SiteVisit;
use Illuminate\Foundation\Http\FormRequest;

class CancelSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $siteVisit = $this->route('siteVisit');

        return $siteVisit instanceof SiteVisit && ($this->user()?->can('cancel', $siteVisit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
