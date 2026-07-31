<?php

namespace App\Http\Requests\Finance;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollectionReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CollectionReceipt::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'booking_payment_schedule_id' => ['nullable', 'integer', 'exists:booking_payment_schedules,id'],
            'receipt_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_mode' => ['required', Rule::in(['cash', 'cheque', 'neft', 'rtgs', 'upi', 'online'])],
            'instrument_number' => ['nullable', 'string', 'max:120', 'required_unless:payment_mode,cash'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:1'],
            'tax_deducted_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $booking = Booking::find($this->integer('booking_id'));
            $schedule = $this->integer('booking_payment_schedule_id')
                ? BookingPaymentSchedule::find($this->integer('booking_payment_schedule_id'))
                : null;

            if (! $booking) {
                return;
            }

            $user = $this->user();
            if (! $user || ! app(CompanyScopeService::class)->allows($user, $booking->company_id)) {
                $validator->errors()->add('booking_id', 'The selected booking is outside your company scope.');
            }

            if (! in_array($booking->status, ['confirmed', 'agreement_pending', 'registered'], true)) {
                $validator->errors()->add('booking_id', 'Collections can be captured only for active confirmed bookings.');
            }

            if ($schedule && $schedule->booking_id !== $booking->id) {
                $validator->errors()->add('booking_payment_schedule_id', 'The selected payment schedule does not belong to this booking.');
            }

            $amount = (float) $this->input('amount');
            $taxDeducted = (float) $this->input('tax_deducted_amount', 0);

            if ($taxDeducted > $amount) {
                $validator->errors()->add('tax_deducted_amount', 'Tax deducted amount cannot exceed the receipt amount.');
            }

            $submittedOrApproved = CollectionReceipt::query()
                ->where('booking_id', $booking->id)
                ->whereIn('status', ['submitted', 'approved'])
                ->sum('amount');

            $bookingOutstanding = max((float) $booking->net_receivable - (float) $submittedOrApproved, 0);
            if ($amount > $bookingOutstanding) {
                $validator->errors()->add('amount', 'Receipt amount exceeds the outstanding booking receivable.');
            }

            if ($schedule) {
                $scheduleSubmittedOrApproved = CollectionReceipt::query()
                    ->where('booking_payment_schedule_id', $schedule->id)
                    ->whereIn('status', ['submitted', 'approved'])
                    ->sum('amount');

                $scheduleOutstanding = max((float) $schedule->amount - (float) $scheduleSubmittedOrApproved, 0);
                if ($amount > $scheduleOutstanding) {
                    $validator->errors()->add('amount', 'Receipt amount exceeds the selected payment schedule outstanding amount.');
                }
            }
        });
    }
}
