<?php

namespace App\Http\Requests\Partner;

use App\Models\Booking;
use App\Services\Partner\PartnerScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PartnerBookingIndexRequest extends FormRequest
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
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'status' => ['nullable', 'string', Rule::in(['draft', 'confirmed', 'cancelled'])],
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
                    ['project_id', 'customer_id', 'status', 'per_page', 'page'],
                );

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $partnerIds = app(PartnerScopeService::class)->activePartnerIdsForUser($this->user());

                if ($this->filled('project_id') && ! $this->partnerHasVisibleBooking($partnerIds, 'project_id', $this->integer('project_id'))) {
                    $validator->errors()->add('project_id', 'The selected project is not available in your partner booking scope.');
                }

                if ($this->filled('customer_id') && ! $this->partnerHasVisibleBooking($partnerIds, 'customer_id', $this->integer('customer_id'))) {
                    $validator->errors()->add('customer_id', 'The selected customer is not available in your partner booking scope.');
                }
            },
        ];
    }

    /**
     * @param array<int, int> $partnerIds
     */
    private function partnerHasVisibleBooking(array $partnerIds, string $field, int $value): bool
    {
        return $partnerIds !== [] && Booking::query()
            ->whereIn('partner_id', $partnerIds)
            ->where($field, $value)
            ->exists();
    }
}
