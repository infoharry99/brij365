<?php

namespace App\Http\Requests\Crm;

use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMarketingCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return (bool) $user?->can('create', \App\Models\MarketingCampaign::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', Rule::in(['digital', 'print', 'outdoor', 'referral', 'channel_partner', 'event', 'portal', 'social', 'email', 'sms', 'other'])],
            'source' => ['required', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active'])],
            'start_on' => ['required', 'date'],
            'end_on' => ['nullable', 'date', 'after_or_equal:start_on'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', app(MoneyInputPolicy::class)->enterpriseAmountMaxRule()],
            'target_leads' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'target_bookings' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'audience_segment' => ['nullable', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
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
                return;
            }

            $belongsToCompany = Project::query()
                ->whereKey($this->integer('project_id'))
                ->where('company_id', $this->integer('company_id'))
                ->exists();

            if (! $belongsToCompany) {
                $validator->errors()->add('project_id', 'The selected project does not belong to the selected company.');
            }
        });
    }
}
