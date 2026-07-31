<?php

namespace App\Http\Requests\Finance;

use App\Models\Booking;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PaymentRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PaymentRequest::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['requested', 'paid', 'cancelled', 'expired', 'failed'])],
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => app(PaginationPolicy::class)->largeRule(),
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
                    ['status', 'booking_id', 'customer_id', 'project_id', 'q', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateCompanyScopedFilter($validator, 'project_id', Project::class);
                $this->validateCompanyScopedFilter($validator, 'booking_id', Booking::class);
                $this->validateCompanyCustomerScope($validator);
            },
        ];
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    private function validateCompanyScopedFilter(Validator $validator, string $field, string $modelClass): void
    {
        if (! $this->filled($field)) {
            return;
        }

        $companyId = $modelClass::query()
            ->whereKey($this->integer($field))
            ->value('company_id');

        if (! app(CompanyScopeService::class)->allows($this->user(), $companyId)) {
            $validator->errors()->add($field, 'The selected record is not available for your company.');
        }
    }

    private function validateCompanyCustomerScope(Validator $validator): void
    {
        if (! $this->filled('customer_id')) {
            return;
        }

        $companyScope = app(CompanyScopeService::class);
        if ($companyScope->hasUnrestrictedCompanyScope($this->user())) {
            return;
        }

        $companyId = $companyScope->companyIdFor($this->user());
        $hasCompanyRecord = $companyId !== null && $companyId > 0 && (
            Booking::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->integer('customer_id'))
                ->exists()
            || PaymentRequest::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->integer('customer_id'))
                ->exists()
        );

        if (! $hasCompanyRecord) {
            $validator->errors()->add('customer_id', 'The selected customer is not available for your company payment requests.');
        }
    }
}
