<?php

namespace App\Http\Requests\Finance;

use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CollectionReceiptIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CollectionReceipt::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'status' => ['nullable', 'string', Rule::in(['submitted', 'approved', 'rejected'])],
            'payment_mode' => ['nullable', 'string', Rule::in(['cash', 'cheque', 'neft', 'rtgs', 'upi', 'online'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
                    ['booking_id', 'project_id', 'customer_id', 'status', 'payment_mode', 'date_from', 'date_to', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user) {
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
            || CollectionReceipt::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->integer('customer_id'))
                ->exists()
        );

        if (! $hasCompanyRecord) {
            $validator->errors()->add('customer_id', 'The selected customer is not available for your company.');
        }
    }
}
