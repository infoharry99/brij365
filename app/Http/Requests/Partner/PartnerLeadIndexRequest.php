<?php

namespace App\Http\Requests\Partner;

use App\Models\Lead;
use App\Services\Partner\PartnerScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartnerLeadIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role?->scope_level === 'partner'
            && $user->can('partner.portal') === true;
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
                    ['stage', 'status', 'project_id', 'per_page', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->filled('project_id')) {
                    return;
                }

                $partnerIds = app(PartnerScopeService::class)->activePartnerIdsForUser($this->user());

                $hasVisibleLead = $partnerIds !== [] && Lead::query()
                    ->whereIn('partner_id', $partnerIds)
                    ->where('project_id', $this->integer('project_id'))
                    ->exists();

                if (! $hasVisibleLead) {
                    $validator->errors()->add('project_id', 'The selected project is not available in your partner lead scope.');
                }
            },
        ];
    }
}
