<?php

namespace App\Http\Requests\Crm;

use App\Models\Project;
use App\Models\User;
use App\Models\MarketingCampaign;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user?->can('crm.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'marketing_campaign_id' => ['nullable', 'integer', 'exists:marketing_campaigns,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'required_without:customer_phone', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'required_without:customer_email', 'string', 'max:32'],
            'source' => ['required', 'string', 'max:80'],
            'stage' => ['required', 'string', 'in:New,Qualified,Site Visit Planned,Negotiation,Booked,Lost'],
            'status' => ['nullable', 'string', 'in:open,won,lost,on_hold'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'gte:budget_min'],
            'expected_value' => ['required', 'numeric', 'min:0'],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();

            if (! $user) {
                return;
            }

            if ($this->filled('company_id') && ! app(CompanyScopeService::class)->allows($user, $this->integer('company_id'))) {
                $validator->errors()->add('company_id', 'The selected company is not available for your user scope.');
            }

            if (! $this->filled('project_id') || ! $this->filled('company_id')) {
                $this->validateCampaignScope($validator);

                return;
            }

            $belongsToCompany = Project::query()
                ->whereKey($this->integer('project_id'))
                ->where('company_id', $this->integer('company_id'))
                ->exists();

            if (! $belongsToCompany) {
                $validator->errors()->add('project_id', 'The selected project does not belong to the selected company.');
            }

            $this->validateCampaignScope($validator);
        });
    }

    private function validateCampaignScope(Validator $validator): void
    {
        if (! $this->filled('marketing_campaign_id') || ! $this->filled('company_id')) {
            return;
        }

        $campaign = MarketingCampaign::query()->whereKey($this->integer('marketing_campaign_id'))->first();

        if (! $campaign || (int) $campaign->company_id !== $this->integer('company_id')) {
            $validator->errors()->add('marketing_campaign_id', 'The selected campaign must belong to the selected company.');

            return;
        }

        if (! in_array($campaign->status, ['active', 'draft'], true)) {
            $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not open for lead attribution.');
        }

        if ($this->filled('project_id') && $campaign->project_id !== null && (int) $campaign->project_id !== $this->integer('project_id')) {
            $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not assigned to the selected project.');
        }
    }
}
