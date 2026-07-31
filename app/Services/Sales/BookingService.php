<?php

namespace App\Services\Sales;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\Lead;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\UnitPricingService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly UnitPricingService $pricing,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?Request $request = null): Booking
    {
        return DB::transaction(function () use ($data, $actor, $request): Booking {
            $unit = ProjectUnit::query()
                ->whereKey($data['project_unit_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $unit->company_id)) {
                throw ValidationException::withMessages([
                    'project_unit_id' => 'The selected unit is outside your company scope.',
                ]);
            }

            if (! $unit->isBookable()) {
                throw ValidationException::withMessages([
                    'project_unit_id' => 'The selected unit is not available for booking.',
                ]);
            }

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $bookedOn = $data['booked_on'] ?? now()->toDateString();
            $quote = $this->pricing->quote($unit, $actor, $bookedOn, $discountAmount);
            $this->pricing->assertDiscountAllowed($unit, $actor, $quote);
            $this->assertCommercialTotals($data, $quote);

            $lead = isset($data['lead_id']) ? Lead::find($data['lead_id']) : null;

            $booking = Booking::create([
                'company_id' => $unit->company_id,
                'project_id' => $unit->project_id,
                'project_unit_id' => $unit->id,
                'unit_price_version_id' => $quote['unit_price_version_id'],
                'customer_id' => $data['customer_id'],
                'lead_id' => $data['lead_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? $lead?->partner_id,
                'booked_by_user_id' => $actor->id,
                'booking_code' => $this->nextCode(),
                'status' => 'confirmed',
                'booked_on' => $bookedOn,
                'agreement_value' => $quote['taxable_amount'],
                'discount_amount' => $quote['discount_amount'],
                'tax_amount' => $quote['tax_amount'],
                'net_receivable' => $quote['total_payable'],
                'booking_amount' => $data['booking_amount'],
                'commercials' => [
                    'pricing_snapshot' => $quote,
                    'unit_total_price' => $quote['total_payable'],
                    'base_rate' => $quote['base_rate'],
                    'discount_amount' => $quote['discount_amount'],
                    'generated_from' => 'sales_booking_service',
                ],
            ]);

            $this->createPaymentSchedules($booking, $data['payment_schedule'] ?? null);

            $unit->forceFill(['status' => 'booked', 'reserved_until' => null])->save();

            if ($lead) {
                $lead->forceFill(['stage' => 'Booked', 'status' => 'won'])->save();
            }

            $this->auditLogger->record(
                $actor,
                'sales.booking.created',
                'Created sales booking',
                $booking,
                [
                    'booking_code' => $booking->booking_code,
                    'unit_code' => $unit->unit_code,
                    'net_receivable' => $booking->net_receivable,
                ],
                $request,
            );

            return $booking->load(['company', 'project', 'unit', 'unitPriceVersion', 'customer', 'lead', 'partner', 'bookedBy', 'paymentSchedules']);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $quote
     */
    private function assertCommercialTotals(array $data, array $quote): void
    {
        $netReceivable = round((float) ($quote['total_payable'] ?? 0), 2);
        $bookingAmount = round((float) ($data['booking_amount'] ?? 0), 2);

        if ($bookingAmount > $netReceivable) {
            throw ValidationException::withMessages([
                'booking_amount' => 'The booking amount cannot exceed the calculated net receivable.',
            ]);
        }

        $requestedSchedules = $data['payment_schedule'] ?? null;
        if (! is_array($requestedSchedules) || $requestedSchedules === []) {
            return;
        }

        $scheduleTotal = 0.0;
        $percentageTotal = 0.0;

        foreach ($requestedSchedules as $schedule) {
            $percentage = round((float) ($schedule['percentage'] ?? 0), 4);
            $amount = array_key_exists('amount', $schedule)
                ? round((float) $schedule['amount'], 2)
                : round(($netReceivable * $percentage) / 100, 2);

            $scheduleTotal += $amount;
            $percentageTotal += $percentage;
        }

        if ($percentageTotal > 100.0001) {
            throw ValidationException::withMessages([
                'payment_schedule' => 'Payment schedule percentages cannot exceed 100%.',
            ]);
        }

        if (round($scheduleTotal, 2) > $netReceivable) {
            throw ValidationException::withMessages([
                'payment_schedule' => 'Payment schedule total cannot exceed the calculated net receivable.',
            ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>>|null $requestedSchedules
     */
    private function createPaymentSchedules(Booking $booking, ?array $requestedSchedules): void
    {
        $schedules = $requestedSchedules ?: [
            ['sequence' => 1, 'milestone' => 'Booking Amount', 'percentage' => 10, 'due_on' => $booking->booked_on?->toDateString()],
            ['sequence' => 2, 'milestone' => 'Agreement', 'percentage' => 20, 'due_on' => $booking->booked_on?->copy()->addDays(15)->toDateString()],
            ['sequence' => 3, 'milestone' => 'Construction Milestone', 'percentage' => 40, 'due_on' => $booking->booked_on?->copy()->addMonths(6)->toDateString()],
            ['sequence' => 4, 'milestone' => 'Possession', 'percentage' => 30, 'due_on' => $booking->booked_on?->copy()->addMonths(18)->toDateString()],
        ];

        foreach ($schedules as $schedule) {
            $percentage = (float) ($schedule['percentage'] ?? 0);
            $amount = array_key_exists('amount', $schedule)
                ? (float) $schedule['amount']
                : round(((float) $booking->net_receivable * $percentage) / 100, 2);

            BookingPaymentSchedule::create([
                'booking_id' => $booking->id,
                'milestone' => $schedule['milestone'],
                'sequence' => $schedule['sequence'],
                'percentage' => $percentage,
                'amount' => $amount,
                'due_on' => $schedule['due_on'] ?? null,
                'status' => 'pending',
            ]);
        }
    }

    private function nextCode(): string
    {
        return sprintf('BK-%04d', Booking::query()->withTrashed()->count() + 1001);
    }
}
