<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CollectionReceiptService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, User $actor, ?Request $request = null): CollectionReceipt
    {
        return DB::transaction(function () use ($data, $actor, $request): CollectionReceipt {
            $booking = Booking::query()
                ->with('paymentSchedules')
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $booking->company_id)) {
                throw ValidationException::withMessages([
                    'booking_id' => 'The selected booking is outside your company scope.',
                ]);
            }

            $schedule = isset($data['booking_payment_schedule_id'])
                ? BookingPaymentSchedule::query()
                    ->whereKey($data['booking_payment_schedule_id'])
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $this->assertOutstandingCapacity($booking, $schedule, (float) $data['amount']);

            $receipt = CollectionReceipt::create([
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule?->id,
                'customer_id' => $booking->customer_id,
                'collected_by_user_id' => $actor->id,
                'receipt_number' => $this->nextReceiptNumber(),
                'status' => 'submitted',
                'receipt_date' => $data['receipt_date'],
                'payment_mode' => $data['payment_mode'],
                'instrument_number' => $data['instrument_number'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'amount' => $data['amount'],
                'tax_deducted_amount' => $data['tax_deducted_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'metadata' => ['submitted_from' => 'finance_collection_service'],
            ]);

            $this->auditLogger->record(
                $actor,
                'finance.collection.submitted',
                'Submitted collection receipt',
                $receipt,
                [
                    'receipt_number' => $receipt->receipt_number,
                    'booking_code' => $booking->booking_code,
                    'amount' => $receipt->amount,
                ],
                $request,
            );

            return $receipt->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(CollectionReceipt $receipt, User $actor, array $data = [], ?Request $request = null): CollectionReceipt
    {
        return DB::transaction(function () use ($receipt, $actor, $data, $request): CollectionReceipt {
            $lockedReceipt = CollectionReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $lockedReceipt->company_id)) {
                throw ValidationException::withMessages([
                    'receipt' => 'The selected collection receipt is outside your company scope.',
                ]);
            }

            if ($lockedReceipt->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'receipt' => 'Only submitted collection receipts can be approved.',
                ]);
            }

            if ($lockedReceipt->collected_by_user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'receipt' => 'The collector cannot approve the same receipt.',
                ]);
            }

            $booking = Booking::query()
                ->whereKey($lockedReceipt->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule = $lockedReceipt->booking_payment_schedule_id
                ? BookingPaymentSchedule::query()
                    ->whereKey($lockedReceipt->booking_payment_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $this->assertOutstandingCapacity($booking, $schedule, (float) $lockedReceipt->amount, $lockedReceipt->id);

            $lockedReceipt->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'metadata' => array_merge($lockedReceipt->metadata ?? [], [
                    'approved_from' => 'finance_collection_service',
                    'approval_note' => $data['note'] ?? null,
                ]),
            ])->save();

            if ($schedule) {
                $this->refreshScheduleStatus($schedule);
            }

            $this->auditLogger->record(
                $actor,
                'finance.collection.approved',
                'Approved collection receipt',
                $lockedReceipt,
                [
                    'receipt_number' => $lockedReceipt->receipt_number,
                    'booking_code' => $booking->booking_code,
                    'amount' => $lockedReceipt->amount,
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            return $lockedReceipt->load($this->relations());
        });
    }

    private function assertOutstandingCapacity(
        Booking $booking,
        ?BookingPaymentSchedule $schedule,
        float $amount,
        ?int $ignoreReceiptId = null,
    ): void {
        $bookingQuery = CollectionReceipt::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['submitted', 'approved']);

        if ($ignoreReceiptId) {
            $bookingQuery->whereKeyNot($ignoreReceiptId);
        }

        $bookingOutstanding = max((float) $booking->net_receivable - (float) $bookingQuery->sum('amount'), 0);
        if ($amount > $bookingOutstanding) {
            throw ValidationException::withMessages([
                'amount' => 'Receipt amount exceeds the outstanding booking receivable.',
            ]);
        }

        if (! $schedule) {
            return;
        }

        if ($schedule->booking_id !== $booking->id) {
            throw ValidationException::withMessages([
                'booking_payment_schedule_id' => 'The selected payment schedule does not belong to this booking.',
            ]);
        }

        $scheduleQuery = CollectionReceipt::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->whereIn('status', ['submitted', 'approved']);

        if ($ignoreReceiptId) {
            $scheduleQuery->whereKeyNot($ignoreReceiptId);
        }

        $scheduleOutstanding = max((float) $schedule->amount - (float) $scheduleQuery->sum('amount'), 0);
        if ($amount > $scheduleOutstanding) {
            throw ValidationException::withMessages([
                'amount' => 'Receipt amount exceeds the selected payment schedule outstanding amount.',
            ]);
        }
    }

    private function refreshScheduleStatus(BookingPaymentSchedule $schedule): void
    {
        $approvedTotal = (float) CollectionReceipt::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->where('status', 'approved')
            ->sum('amount');

        $status = match (true) {
            $approvedTotal <= 0 => 'pending',
            $approvedTotal >= (float) $schedule->amount => 'paid',
            default => 'partially_paid',
        };

        $schedule->forceFill(['status' => $status])->save();
    }

    private function nextReceiptNumber(): string
    {
        return sprintf('RCPT-%04d', CollectionReceipt::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectedBy', 'approvedBy'];
    }
}
