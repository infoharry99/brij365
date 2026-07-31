<?php

namespace App\Http\Requests\Finance;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\PaymentRequest;
use App\Services\Security\CompanyScopeService;
use App\Support\MoneyInputPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentRequest::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', Rule::exists('bookings', 'id')],
            'booking_payment_schedule_id' => ['nullable', 'integer', Rule::exists('booking_payment_schedules', 'id')],
            'amount' => ['required', 'numeric', 'min:1', app(MoneyInputPolicy::class)->paymentAmountMaxRule()],
            'purpose' => ['required', 'string', 'max:160'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'gateway_provider' => ['prohibited'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $booking = Booking::query()->find($this->integer('booking_id'));
            $schedule = $this->integer('booking_payment_schedule_id')
                ? BookingPaymentSchedule::query()->find($this->integer('booking_payment_schedule_id'))
                : null;

            if (! $booking) {
                return;
            }

            if (! $this->user() || ! app(CompanyScopeService::class)->allows($this->user(), $booking->company_id)) {
                $validator->errors()->add('booking_id', 'The booking is outside your company scope.');
            }

            if (! in_array($booking->status, ['confirmed', 'agreement_pending', 'registered'], true)) {
                $validator->errors()->add('booking_id', 'Payment requests can be created only for active confirmed bookings.');
            }

            if ($schedule && $schedule->booking_id !== $booking->id) {
                $validator->errors()->add('booking_payment_schedule_id', 'The selected schedule does not belong to this booking.');
            }

            $amount = (float) $this->input('amount');

            $bookingOutstanding = max((float) $booking->net_receivable - (float) CollectionReceipt::query()
                ->where('booking_id', $booking->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->sum('amount'), 0);

            if ($amount > $bookingOutstanding) {
                $validator->errors()->add('amount', 'Payment request amount exceeds booking outstanding amount.');
            }

            if ($schedule) {
                $scheduleOutstanding = max((float) $schedule->amount - (float) CollectionReceipt::query()
                    ->where('booking_payment_schedule_id', $schedule->id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->sum('amount'), 0);

                if ($amount > $scheduleOutstanding) {
                    $validator->errors()->add('amount', 'Payment request amount exceeds schedule outstanding amount.');
                }

                $activeRequestExists = PaymentRequest::query()
                    ->where('booking_payment_schedule_id', $schedule->id)
                    ->where('status', 'requested')
                    ->exists();

                if ($activeRequestExists) {
                    $validator->errors()->add('booking_payment_schedule_id', 'An active payment request already exists for this schedule.');
                }
            }
        });
    }
}