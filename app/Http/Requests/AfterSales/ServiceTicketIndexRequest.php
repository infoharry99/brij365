<?php

namespace App\Http\Requests\AfterSales;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceTicketIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ServiceTicket::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', 'string', Rule::in(['open', 'assigned', 'in_progress', 'resolved', 'closed'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'category' => ['nullable', 'string', Rule::in(['defect', 'maintenance', 'billing', 'documentation', 'society', 'other'])],
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
                    [
                        'project_id',
                        'booking_id',
                        'customer_id',
                        'assigned_to_user_id',
                        'status',
                        'priority',
                        'category',
                        'per_page',
                        'page',
                    ],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->isBuyerPortalUser()) {
                    $this->validateBuyerBookingScope($validator);
                    $this->validateBuyerCustomerScope($validator);

                    return;
                }

                $this->validateCompanyScopedFilter($validator, 'project_id', Project::class);
                $this->validateCompanyScopedFilter($validator, 'booking_id', Booking::class);
                $this->validateCompanyScopedFilter($validator, 'assigned_to_user_id', User::class);
                $this->validateCompanyCustomerScope($validator);
            },
        ];
    }

    private function validateBuyerBookingScope(Validator $validator): void
    {
        if (! $this->filled('booking_id')) {
            return;
        }

        $portalUserId = Booking::query()
            ->with('customer:id,portal_user_id')
            ->whereKey($this->integer('booking_id'))
            ->first()
            ?->customer
            ?->portal_user_id;

        if ((int) $portalUserId !== (int) $this->user()?->id) {
            $validator->errors()->add('booking_id', 'The selected booking is not available in this buyer portal.');
        }
    }

    private function validateBuyerCustomerScope(Validator $validator): void
    {
        if (! $this->filled('customer_id')) {
            return;
        }

        $portalUserId = Customer::query()
            ->whereKey($this->integer('customer_id'))
            ->value('portal_user_id');

        if ((int) $portalUserId !== (int) $this->user()?->id) {
            $validator->errors()->add('customer_id', 'The selected customer is not available in this buyer portal.');
        }
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

        $user = $this->user();

        if (! $user || ! app(CompanyScopeService::class)->allows($user, $companyId)) {
            $validator->errors()->add($field, 'The selected record is not available for your company.');
        }
    }

    private function validateCompanyCustomerScope(Validator $validator): void
    {
        if (! $this->filled('customer_id')) {
            return;
        }

        $user = $this->user();

        if (! $user) {
            return;
        }

        $companyId = app(CompanyScopeService::class)->companyIdFor($user);

        if ($companyId === 0) {
            $validator->errors()->add('customer_id', 'The selected customer is not available for your company.');

            return;
        }

        if ($companyId === null) {
            return;
        }

        $hasCompanyRecord = ServiceTicket::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $this->integer('customer_id'))
            ->exists()
            || Booking::query()
                ->where('company_id', $companyId)
                ->where('customer_id', $this->integer('customer_id'))
                ->exists();

        if (! $hasCompanyRecord) {
            $validator->errors()->add('customer_id', 'The selected customer is not available for your company.');
        }
    }

    private function isBuyerPortalUser(): bool
    {
        $user = $this->user();

        return $user?->role?->slug === 'buyer' && $user->hasPermission('buyer.view');
    }
}
