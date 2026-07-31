<?php

namespace App\Http\Requests\Possession;

use App\Models\HandoverSnag;
use App\Models\PossessionHandover;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHandoverSnagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HandoverSnag::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'possession_handover_id' => ['required', 'integer', Rule::exists('possession_handovers', 'id')],
            'area' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
            'severity' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'max:5000'],
            'target_resolution_on' => ['nullable', 'date', 'after_or_equal:today'],
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $handover = PossessionHandover::query()->whereKey($this->integer('possession_handover_id'))->first();
                $user = $this->user();

                if (
                    ! $handover
                    || ! $user
                    || ! app(CompanyScopeService::class)->allows($user, $handover->company_id)
                    || $handover->status === 'completed'
                ) {
                    $validator->errors()->add('possession_handover_id', 'The selected handover is not open for snag reporting.');
                }
            },
        ];
    }
}
