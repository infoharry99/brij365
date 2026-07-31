<?php

namespace App\Http\Requests\Crm;

use App\Models\MarketingCampaign;
use App\Models\ProspectInquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConvertProspectInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProspectInquiry|null $prospectInquiry */
        $prospectInquiry = $this->route('prospectInquiry');

        return $prospectInquiry instanceof ProspectInquiry
            && $this->user()?->can('convert', $prospectInquiry) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'stage' => ['nullable', 'string', Rule::in(['New', 'Qualified', 'Site Visit Planned', 'Negotiation'])],
            'follow_up_at' => ['nullable', 'date'],
            'marketing_campaign_id' => ['nullable', 'integer', Rule::exists('marketing_campaigns', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var ProspectInquiry|null $prospectInquiry */
                $prospectInquiry = $this->route('prospectInquiry');

                if (! $prospectInquiry instanceof ProspectInquiry || ! $this->filled('marketing_campaign_id')) {
                    return;
                }

                $campaign = MarketingCampaign::query()->whereKey($this->integer('marketing_campaign_id'))->first();

                if (! $campaign || (int) $campaign->company_id !== (int) $prospectInquiry->company_id) {
                    $validator->errors()->add('marketing_campaign_id', 'The selected campaign must belong to the inquiry company.');

                    return;
                }

                if ($campaign->project_id !== null && (int) $campaign->project_id !== (int) $prospectInquiry->project_id) {
                    $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not assigned to the inquiry project.');
                }

                if (! in_array($campaign->status, ['active', 'draft'], true)) {
                    $validator->errors()->add('marketing_campaign_id', 'The selected campaign is not open for lead attribution.');
                }
            },
        ];
    }
}
