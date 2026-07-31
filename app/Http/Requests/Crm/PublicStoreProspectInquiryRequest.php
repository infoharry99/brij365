<?php

namespace App\Http\Requests\Crm;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicStoreProspectInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'source' => ['nullable', 'string', 'max:80'],
            'channel' => ['nullable', 'string', Rule::in([
                'website',
                'mobile_app',
                'buyer_portal',
                'landing_page',
                'channel_partner',
                'referral',
                'social',
                'whatsapp',
                'phone',
                'other',
            ])],
            'preferred_contact_method' => ['nullable', 'string', Rule::in(['phone', 'email', 'whatsapp'])],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'message' => ['nullable', 'string', 'max:2000'],
            'consent_to_contact' => ['accepted'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('project_id')) {
                    return;
                }

                $isActive = Project::query()
                    ->whereKey($this->integer('project_id'))
                    ->where('status', 'active')
                    ->exists();

                if (! $isActive) {
                    $validator->errors()->add('project_id', 'The selected project is not open for public prospect inquiries.');
                }
            },
        ];
    }
}
