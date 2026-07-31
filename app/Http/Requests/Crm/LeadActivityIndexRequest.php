<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeadActivityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeadActivity::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'marketing_campaign_id' => ['nullable', 'integer', Rule::exists('marketing_campaigns', 'id')],
            'activity_type' => ['nullable', 'string', Rule::in(['created', 'note', 'call', 'email', 'site_visit', 'qualification', 'stage_change', 'campaign_response', 'follow_up', 'booking'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['lead_id', 'project_id', 'marketing_campaign_id', 'activity_type', 'date_from', 'date_to', 'q', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($this->filled('lead_id')) {
                    $companyId = Lead::query()->whereKey($this->integer('lead_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                        $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');
                    }
                }

                if ($this->filled('project_id')) {
                    $companyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('marketing_campaign_id')) {
                    $companyId = MarketingCampaign::query()->whereKey($this->integer('marketing_campaign_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                        $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not available for your company.');
                    }
                }
            },
        ];
    }
}
