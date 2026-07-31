<?php

namespace App\Http\Requests\Crm;

use App\Models\MarketingCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarketingCampaignStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MarketingCampaign|null $marketingCampaign */
        $marketingCampaign = $this->route('marketingCampaign');

        return $marketingCampaign instanceof MarketingCampaign
            && $this->user()?->can('update', $marketingCampaign) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['active', 'paused', 'completed', 'archived'])],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
