<?php

namespace App\Http\Requests\Crm;

use App\Models\Lead;
use App\Models\LeadQualification;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LeadQualificationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', LeadQualification::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'status' => ['nullable', 'string', Rule::in(['qualified', 'nurture', 'disqualified'])],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_from' => ['nullable', 'date'],
            'expected_to' => ['nullable', 'date', 'after_or_equal:expected_from'],
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
                    ['lead_id', 'status', 'min_score', 'expected_from', 'expected_to', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->filled('lead_id') || ! $this->user()) {
                    return;
                }

                $lead = Lead::query()->whereKey($this->integer('lead_id'))->first();

                if ($lead && ! app(CompanyScopeService::class)->allows($this->user(), $lead->company_id)) {
                    $validator->errors()->add('lead_id', 'The selected lead is not available for your company.');
                }
            },
        ];
    }
}
