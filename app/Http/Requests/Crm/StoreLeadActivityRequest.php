<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingCampaign;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', LeadActivity::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['required', 'integer', 'exists:leads,id'],
            'marketing_campaign_id' => ['nullable', 'integer', 'exists:marketing_campaigns,id'],
            'activity_type' => ['required', 'string', Rule::in(['note', 'call', 'email', 'campaign_response', 'follow_up'])],
            'activity_at' => ['nullable', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'outcome' => ['nullable', 'string', 'max:80'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:activity_at'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user || ! $this->filled('lead_id')) {
                return;
            }

            $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();

            if (! $lead || ! app(CompanyScopeService::class)->allows($user, $lead->company_id)) {
                $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');

                return;
            }

            if (! $this->filled('marketing_campaign_id')) {
                return;
            }

            $campaign = MarketingCampaign::query()->whereKey($this->integer('marketing_campaign_id'))->first();

            if (! $campaign || $campaign->company_id !== $lead->company_id) {
                $validator->errors()->add('marketing_campaign_id', 'The selected campaign must belong to the lead company.');

                return;
            }

            if ($campaign->project_id !== null && $lead->project_id !== null && (int) $campaign->project_id !== (int) $lead->project_id) {
                $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not assigned to the lead project.');
            }
        });
    }
}
