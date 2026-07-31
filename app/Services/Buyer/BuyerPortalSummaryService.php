<?php

namespace App\Services\Buyer;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\Customer;
use App\Models\ManagedDocument;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuyerPortalSummaryService
{
    /**
     * @return array<string, mixed>
     */
    public function summaryFor(User $user): array
    {
        $customer = $user->customer()->first();

        if (! $customer) {
            return $this->emptySummary();
        }

        return $this->summaryForCustomer($customer);
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForCustomer(Customer $customer): array
    {
        $bookingIds = $this->customerBookingIds($customer);
        $scheduledTotal = $this->scheduledPaymentTotal($customer);
        $paidTotal = (float) CollectionReceipt::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->sum('amount');
        $documentsCount = $this->documentQuery($customer, $bookingIds)->count();
        $openTicketsCount = ServiceTicket::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'assigned', 'in_progress'])
            ->count();

        return [
            'customer' => [
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status,
            ],
            'bookings_count' => count($bookingIds),
            'open_tickets_count' => $openTicketsCount,
            'approved_receipts_total' => $paidTotal,
            'scheduled_payments_total' => $scheduledTotal,
            'outstanding_amount' => max($scheduledTotal - $paidTotal, 0),
            'documents_count' => $documentsCount,
            'next_due' => $this->nextDue($customer),
            'recent_bookings' => $this->recentBookings($customer),
            'payment_schedule' => $this->paymentSchedule($customer),
            'recent_receipts' => $this->recentReceipts($customer),
            'documents' => $this->documents($customer, $bookingIds),
            'service_tickets' => $this->serviceTickets($customer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'customer' => null,
            'bookings_count' => 0,
            'open_tickets_count' => 0,
            'approved_receipts_total' => 0,
            'scheduled_payments_total' => 0,
            'outstanding_amount' => 0,
            'documents_count' => 0,
            'next_due' => null,
            'recent_bookings' => [],
            'payment_schedule' => [],
            'recent_receipts' => [],
            'documents' => [],
            'service_tickets' => [],
        ];
    }

    /**
     * @return array<int, int>
     */
    private function customerBookingIds(Customer $customer): array
    {
        return Booking::query()
            ->where('customer_id', $customer->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function scheduledPaymentTotal(Customer $customer): float
    {
        return (float) Booking::query()
            ->where('customer_id', $customer->id)
            ->withSum('paymentSchedules as scheduled_total', 'amount')
            ->get()
            ->sum('scheduled_total');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextDue(Customer $customer): ?array
    {
        $schedule = BookingPaymentSchedule::query()
            ->with('booking:id,booking_code,customer_id')
            ->whereHas('booking', fn (Builder $query) => $query->where('customer_id', $customer->id))
            ->where('status', 'pending')
            ->orderBy('due_on')
            ->first();

        if (! $schedule) {
            return null;
        }

        return [
            'booking_code' => $schedule->booking?->booking_code,
            'milestone' => $schedule->milestone,
            'amount' => (float) $schedule->amount,
            'due_on' => $schedule->due_on?->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentBookings(Customer $customer): array
    {
        return Booking::query()
            ->with(['company:id,code,name', 'project:id,code,name,city', 'unit:id,unit_code,tower,floor,unit_number,unit_type,total_price', 'paymentSchedules'])
            ->where('customer_id', $customer->id)
            ->latest('booked_on')
            ->limit(3)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'booked_on' => $booking->booked_on?->toDateString(),
                'net_receivable' => (float) $booking->net_receivable,
                'project' => $booking->project ? [
                    'code' => $booking->project->code,
                    'name' => $booking->project->name,
                    'city' => $booking->project->city,
                ] : null,
                'unit' => $booking->unit ? [
                    'unit_code' => $booking->unit->unit_code,
                    'tower' => $booking->unit->tower,
                    'floor' => $booking->unit->floor,
                    'unit_number' => $booking->unit->unit_number,
                    'unit_type' => $booking->unit->unit_type,
                    'total_price' => (float) $booking->unit->total_price,
                ] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentSchedule(Customer $customer): array
    {
        return BookingPaymentSchedule::query()
            ->with('booking:id,booking_code,customer_id')
            ->whereHas('booking', fn (Builder $query) => $query->where('customer_id', $customer->id))
            ->orderBy('booking_id')
            ->orderBy('sequence')
            ->get()
            ->map(fn (BookingPaymentSchedule $schedule): array => [
                'id' => $schedule->id,
                'booking_code' => $schedule->booking?->booking_code,
                'sequence' => $schedule->sequence,
                'milestone' => $schedule->milestone,
                'percentage' => (float) $schedule->percentage,
                'amount' => (float) $schedule->amount,
                'due_on' => $schedule->due_on?->toDateString(),
                'status' => $schedule->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentReceipts(Customer $customer): array
    {
        return CollectionReceipt::query()
            ->with(['booking:id,booking_code,customer_id', 'project:id,code,name'])
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->latest('receipt_date')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (CollectionReceipt $receipt): array => [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'booking_code' => $receipt->booking?->booking_code,
                'project' => $receipt->project?->name,
                'receipt_date' => $receipt->receipt_date?->toDateString(),
                'payment_mode' => $receipt->payment_mode,
                'amount' => (float) $receipt->amount,
                'status' => $receipt->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $bookingIds
     */
    private function documentQuery(?Customer $customer, array $bookingIds): Builder
    {
        return ManagedDocument::query()
            ->where('status', 'approved')
            ->where('is_current', true)
            ->where(function (Builder $query) use ($customer, $bookingIds): void {
                if ($customer) {
                    $query->where(function (Builder $customerQuery) use ($customer): void {
                        $customerQuery
                            ->where('owner_type', 'customer')
                            ->where('owner_id', $customer->id);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }

                if ($bookingIds !== []) {
                    $query->orWhere(function (Builder $bookingQuery) use ($bookingIds): void {
                        $bookingQuery
                            ->where('owner_type', 'booking')
                            ->whereIn('owner_id', $bookingIds);
                    });
                }
            });
    }

    /**
     * @param array<int, int> $bookingIds
     * @return array<int, array<string, mixed>>
     */
    private function documents(Customer $customer, array $bookingIds): array
    {
        return $this->documentQuery($customer, $bookingIds)
            ->with('category:id,code,name')
            ->latest('approved_at')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ManagedDocument $document): array => [
                'id' => $document->id,
                'document_number' => $document->document_number,
                'title' => $document->title,
                'owner_type' => $document->owner_type,
                'status' => $document->status,
                'category' => $document->category?->name,
                'download_url' => route('documents.download', $document, false),
                'expires_on' => $document->expires_on?->toDateString(),
                'version' => $document->version,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serviceTickets(Customer $customer): array
    {
        return ServiceTicket::query()
            ->with(['booking:id,booking_code,customer_id'])
            ->where('customer_id', $customer->id)
            ->orderByRaw("case when status in ('open', 'assigned', 'in_progress') then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->limit(10)
            ->get()
            ->map(fn (ServiceTicket $ticket): array => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'booking_code' => $ticket->booking?->booking_code,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
