<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\MarketingCampaign;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeadIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Lead::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['nullable', 'string', Rule::in([
                'New',
                'Qualified',
                'Nurture',
                'Disqualified',
                'Site Visit Planned',
                'Site Visit Scheduled',
                'Site Visit Done',
                'Follow-up',
                'Negotiation',
                'Booked',
                'Lost',
            ])],
            'status' => ['nullable', 'string', Rule::in(['open', 'won', 'lost', 'on_hold'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'marketing_campaign_id' => ['nullable', 'integer', Rule::exists('marketing_campaigns', 'id')],
            'source' => ['nullable', 'string', 'max:80'],
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
                    ['stage', 'status', 'project_id', 'marketing_campaign_id', 'source', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()
                        ->whereKey($this->integer('project_id'))
                        ->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if (! $this->filled('marketing_campaign_id')) {
                    return;
                }

                $campaignCompanyId = MarketingCampaign::query()
                    ->whereKey($this->integer('marketing_campaign_id'))
                    ->value('company_id');

                if (! app(CompanyScopeService::class)->allows($user, $campaignCompanyId)) {
                    $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not available for your company.');
                }
            },
        ];
    }
}
