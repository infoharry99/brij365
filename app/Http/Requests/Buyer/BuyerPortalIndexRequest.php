<?php

namespace App\Http\Requests\Buyer;

use App\Models\Booking;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BuyerPortalIndexRequest extends FormRequest
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ROUTE_FILTERS = [
        'buyer.bookings.index' => ['booking_id', 'status', 'per_page', 'page'],
        'buyer.receipts.index' => ['booking_id', 'status', 'per_page', 'page'],
        'buyer.payment-requests.index' => ['booking_id', 'status', 'per_page', 'page'],
        'buyer.documents.index' => ['owner_type', 'status', 'per_page', 'page'],
        'buyer.service-tickets.index' => ['booking_id', 'status', 'category', 'priority', 'per_page', 'page'],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROUTE_STATUSES = [
        'buyer.bookings.index' => ['draft', 'confirmed', 'cancelled'],
        'buyer.receipts.index' => ['approved'],
        'buyer.payment-requests.index' => ['requested', 'approved', 'paid', 'expired', 'cancelled'],
        'buyer.documents.index' => ['approved'],
        'buyer.service-tickets.index' => ['open', 'assigned', 'in_progress', 'resolved', 'closed'],
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role?->scope_level === 'self'
            && $user->role?->slug === 'buyer'
            && $user->can('buyer.view') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', Rule::exists('bookings', 'id')],
            'status' => ['nullable', 'string', Rule::in([
                'draft',
                'confirmed',
                'cancelled',
                'requested',
                'approved',
                'paid',
                'expired',
                'open',
                'assigned',
                'in_progress',
                'resolved',
                'closed',
            ])],
            'category' => ['nullable', 'string', Rule::in(['defect', 'maintenance', 'billing', 'documentation', 'society', 'other'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'owner_type' => ['nullable', 'string', Rule::in(['customer', 'booking'])],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $routeName = (string) $this->route()?->getName();
                $allowedFilters = self::ROUTE_FILTERS[$routeName] ?? [];

                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), $allowedFilters);

                foreach (['booking_id', 'status', 'category', 'priority', 'owner_type'] as $filter) {
                    if ($this->filled($filter) && $allowedFilters !== [] && ! in_array($filter, $allowedFilters, true)) {
                        $validator->errors()->add($filter, 'The '.$filter.' filter is not available for this buyer portal endpoint.');
                    }
                }

                if (
                    ! $validator->errors()->has('status')
                    && $this->filled('status')
                    && isset(self::ROUTE_STATUSES[$routeName])
                ) {
                    $status = (string) $this->input('status');

                    if (! in_array($status, self::ROUTE_STATUSES[$routeName], true)) {
                        $validator->errors()->add('status', 'The selected status is not valid for this buyer portal endpoint.');
                    }
                }

                if (! $this->filled('booking_id')) {
                    return;
                }

                if ($validator->errors()->has('booking_id')) {
                    return;
                }

                $bookingPortalUserId = Booking::query()
                    ->with('customer:id,portal_user_id')
                    ->whereKey($this->integer('booking_id'))
                    ->first()
                    ?->customer
                    ?->portal_user_id;

                if ((int) $bookingPortalUserId !== (int) $this->user()?->id) {
                    $validator->errors()->add('booking_id', 'The selected booking is not available in this buyer portal.');
                }
            },
        ];
    }
}
