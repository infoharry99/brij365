<?php

namespace App\Http\Requests\AfterSales;

use App\Models\Booking;
use App\Models\ServiceTicket;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceTicket::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'category' => ['required', 'string', Rule::in(['defect', 'maintenance', 'billing', 'documentation', 'society', 'other'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'source' => ['nullable', 'string', Rule::in(['portal', 'phone', 'email', 'internal'])],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.name' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.url' => ['required_with:attachments', 'string', 'max:1024'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $booking = Booking::query()
                    ->with('customer')
                    ->whereKey($this->integer('booking_id'))
                    ->first();

                if (! $booking || $booking->status !== 'confirmed') {
                    $validator->errors()->add('booking_id', 'The selected confirmed booking is not available for service requests.');

                    return;
                }

                if ($this->isBuyerPortalUser()) {
                    if ((int) $booking->customer?->portal_user_id !== $this->user()?->id) {
                        $validator->errors()->add('booking_id', 'Buyer users can raise service tickets only for their own bookings.');
                    }

                    return;
                }

                $user = $this->user();

                if (! $user || ! app(CompanyScopeService::class)->allows($user, $booking->company_id)) {
                    $validator->errors()->add('booking_id', 'The selected booking is not available for your company.');
                }
            },
        ];
    }

    private function isBuyerPortalUser(): bool
    {
        $user = $this->user();

        return $user?->role?->slug === 'buyer' && $user->hasPermission('buyer.view');
    }
}
