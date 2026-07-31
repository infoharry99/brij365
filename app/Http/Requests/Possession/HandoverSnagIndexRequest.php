<?php

namespace App\Http\Requests\Possession;

use App\Models\HandoverSnag;
use App\Models\PossessionHandover;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HandoverSnagIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', HandoverSnag::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'possession_handover_id' => ['nullable', 'integer', 'exists:possession_handovers,id'],
            'status' => ['nullable', Rule::in(['open', 'resolved'])],
            'severity' => ['nullable', Rule::in(['low', 'medium', 'high', 'critical'])],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['possession_handover_id', 'status', 'severity', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty() || ! $this->filled('possession_handover_id')) {
                return;
            }

            $handover = PossessionHandover::find($this->integer('possession_handover_id'));
            $user = $this->user();

            if ($handover && $user && ! app(CompanyScopeService::class)->allows($user, $handover->company_id)) {
                $validator->errors()->add('possession_handover_id', 'The selected handover is outside your company scope.');
            }
        });
    }
}
