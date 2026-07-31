<?php

namespace App\Services\Partner;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CommissionItem;
use App\Models\Lead;
use App\Models\ManagedDocument;
use App\Models\Partner;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PartnerDashboardService
{
    public function __construct(private readonly PartnerScopeService $partnerScope)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryFor(User $user, int $limit = 10): array
    {
        $partnerIds = $this->partnerScope->activePartnerIdsForUser($user);

        if ($partnerIds === []) {
            return $this->emptySummary();
        }

        $limit = max(1, min($limit, 25));

        return [
            'scope' => [
                'partner_ids' => $partnerIds,
                'partners' => $this->partners($partnerIds),
            ],
            'metrics' => $this->metrics($partnerIds),
            'lead_stage_summary' => $this->leadStageSummary($partnerIds),
            'my_leads' => $this->recentLeads($partnerIds, $limit),
            'site_visits' => $this->recentSiteVisits($partnerIds, $limit),
            'bookings' => $this->recentBookings($partnerIds, $limit),
            'collections_follow_up' => $this->collectionFollowUps($partnerIds, $limit),
            'commission_summary' => $this->commissionSummary($partnerIds, $limit),
            'documents' => $this->partnerDocuments($partnerIds, $limit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(): array
    {
        return [
            'scope' => [
                'partner_ids' => [],
                'partners' => [],
            ],
            'metrics' => [
                'leads' => 0,
                'open_leads' => 0,
                'site_visits' => 0,
                'bookings' => 0,
                'open_collection_amount' => '0.00',
                'approved_commission_amount' => '0.00',
            ],
            'lead_stage_summary' => [],
            'my_leads' => [],
            'site_visits' => [],
            'bookings' => [],
            'collections_follow_up' => [],
            'commission_summary' => [
                'total_items' => 0,
                'approved_amount' => '0.00',
                'pending_amount' => '0.00',
                'paid_amount' => '0.00',
                'items' => [],
            ],
            'documents' => [],
        ];
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function partners(array $partnerIds): array
    {
        return Partner::query()
            ->whereIn('id', $partnerIds)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'partner_type', 'status'])
            ->map(fn (Partner $partner): array => [
                'id' => $partner->id,
                'code' => $partner->code,
                'name' => $partner->name,
                'type' => $partner->partner_type,
                'status' => $partner->status,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<string, mixed>
     */
    private function metrics(array $partnerIds): array
    {
        return [
            'leads' => Lead::query()->whereIn('partner_id', $partnerIds)->count(),
            'open_leads' => Lead::query()
                ->whereIn('partner_id', $partnerIds)
                ->whereNotIn('status', ['won', 'lost'])
                ->count(),
            'site_visits' => $this->scopedSiteVisitQuery($partnerIds)->count(),
            'bookings' => Booking::query()->whereIn('partner_id', $partnerIds)->count(),
            'open_collection_amount' => $this->formatMoney($this->openCollectionAmount($partnerIds)),
            'approved_commission_amount' => $this->formatMoney(
                (float) CommissionItem::query()
                    ->whereIn('partner_id', $partnerIds)
                    ->where('status', 'approved')
                    ->sum('commission_amount')
            ),
        ];
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function leadStageSummary(array $partnerIds): array
    {
        return Lead::query()
            ->select('stage', DB::raw('COUNT(*) as lead_count'), DB::raw('SUM(expected_value) as expected_value_total'))
            ->whereIn('partner_id', $partnerIds)
            ->groupBy('stage')
            ->orderBy('stage')
            ->get()
            ->map(fn (Lead $lead): array => [
                'stage' => $lead->stage,
                'lead_count' => (int) $lead->lead_count,
                'expected_value_total' => $this->formatMoney((float) $lead->expected_value_total),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function recentLeads(array $partnerIds, int $limit): array
    {
        return Lead::query()
            ->with(['company:id,code,name', 'project:id,code,name', 'customer:id,code,name,email,phone'])
            ->whereIn('partner_id', $partnerIds)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead): array => [
                'id' => $lead->id,
                'lead_code' => $lead->lead_code,
                'customer' => $lead->customer?->name,
                'project' => $lead->project?->name,
                'source' => $lead->source,
                'stage' => $lead->stage,
                'status' => $lead->status,
                'expected_value' => $this->formatMoney((float) $lead->expected_value),
                'follow_up_at' => $lead->follow_up_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function recentSiteVisits(array $partnerIds, int $limit): array
    {
        return $this->scopedSiteVisitQuery($partnerIds)
            ->with(['project:id,code,name', 'customer:id,code,name,email,phone', 'lead:id,lead_code,partner_id', 'assignedTo:id,name'])
            ->latest('scheduled_at')
            ->limit($limit)
            ->get()
            ->map(fn (SiteVisit $visit): array => [
                'id' => $visit->id,
                'visit_number' => $visit->visit_number,
                'lead_code' => $visit->lead?->lead_code,
                'customer' => $visit->customer?->name,
                'project' => $visit->project?->name,
                'assigned_to' => $visit->assignedTo?->name,
                'status' => $visit->status,
                'scheduled_at' => $visit->scheduled_at?->toIso8601String(),
                'visit_mode' => $visit->visit_mode,
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function recentBookings(array $partnerIds, int $limit): array
    {
        return Booking::query()
            ->with(['project:id,code,name', 'unit:id,unit_code,unit_number', 'customer:id,code,name,email,phone'])
            ->whereIn('partner_id', $partnerIds)
            ->latest('booked_on')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Booking $booking): array => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'customer' => $booking->customer?->name,
                'project' => $booking->project?->name,
                'unit' => $booking->unit?->unit_code,
                'status' => $booking->status,
                'booked_on' => $booking->booked_on?->toDateString(),
                'net_receivable' => $this->formatMoney((float) $booking->net_receivable),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function collectionFollowUps(array $partnerIds, int $limit): array
    {
        return BookingPaymentSchedule::query()
            ->with([
                'booking:id,booking_code,partner_id,project_id,customer_id',
                'booking.project:id,code,name',
                'booking.customer:id,code,name,email,phone',
            ])
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('partner_id', $partnerIds))
            ->whereIn('status', ['pending', 'due', 'overdue', 'partially_paid'])
            ->orderBy('due_on')
            ->limit($limit)
            ->get()
            ->map(fn (BookingPaymentSchedule $schedule): array => [
                'id' => $schedule->id,
                'booking_code' => $schedule->booking?->booking_code,
                'customer' => $schedule->booking?->customer?->name,
                'project' => $schedule->booking?->project?->name,
                'milestone' => $schedule->milestone,
                'status' => $schedule->status,
                'due_on' => $schedule->due_on?->toDateString(),
                'amount' => $this->formatMoney((float) $schedule->amount),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<string, mixed>
     */
    private function commissionSummary(array $partnerIds, int $limit): array
    {
        $items = CommissionItem::query()
            ->with(['booking:id,booking_code,partner_id', 'lead:id,lead_code,partner_id', 'run:id,run_number,status'])
            ->whereIn('partner_id', $partnerIds)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (CommissionItem $item): array => [
                'id' => $item->id,
                'run_number' => $item->run?->run_number,
                'booking_code' => $item->booking?->booking_code,
                'lead_code' => $item->lead?->lead_code,
                'period' => sprintf('%04d-%02d', $item->period_year, $item->period_month),
                'status' => $item->status,
                'eligible_amount' => $this->formatMoney((float) $item->eligible_amount),
                'commission_amount' => $this->formatMoney((float) $item->commission_amount),
                'payroll_included_at' => $item->payroll_included_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'total_items' => CommissionItem::query()->whereIn('partner_id', $partnerIds)->count(),
            'approved_amount' => $this->formatMoney($this->commissionAmountByStatus($partnerIds, ['approved'])),
            'pending_amount' => $this->formatMoney($this->commissionAmountByStatus($partnerIds, ['draft', 'generated', 'pending_approval'])),
            'paid_amount' => $this->formatMoney($this->commissionAmountByStatus($partnerIds, ['payroll_included', 'paid'])),
            'items' => $items,
        ];
    }

    /**
     * @param array<int, int> $partnerIds
     * @return array<int, array<string, mixed>>
     */
    private function partnerDocuments(array $partnerIds, int $limit): array
    {
        $bookingIds = Booking::query()
            ->whereIn('partner_id', $partnerIds)
            ->pluck('id')
            ->all();

        return ManagedDocument::query()
            ->with(['category:id,code,name'])
            ->where('is_current', true)
            ->whereIn('status', ['approved', 'active'])
            ->where(function (Builder $query) use ($bookingIds, $partnerIds): void {
                $query->where(function (Builder $bookingQuery) use ($bookingIds): void {
                    $bookingQuery->where('owner_type', 'booking')
                        ->whereIn('owner_id', $bookingIds ?: [0]);
                })->orWhere(function (Builder $partnerQuery) use ($partnerIds): void {
                    $partnerQuery->where('owner_type', 'partner')
                        ->whereIn('owner_id', $partnerIds);
                });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (ManagedDocument $document): array => [
                'id' => $document->id,
                'document_number' => $document->document_number,
                'title' => $document->title,
                'category' => $document->category?->name,
                'owner_type' => $document->owner_type,
                'status' => $document->status,
                'version' => $document->version,
                'expires_on' => $document->expires_on?->toDateString(),
                'is_expired' => $document->isExpired(),
                'download_url' => route('documents.download', $document, false),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<int, int> $partnerIds
     */
    private function scopedSiteVisitQuery(array $partnerIds): Builder
    {
        return SiteVisit::query()
            ->whereHas('lead', fn (Builder $query) => $query->whereIn('partner_id', $partnerIds));
    }

    /**
     * @param array<int, int> $partnerIds
     */
    private function openCollectionAmount(array $partnerIds): float
    {
        return (float) BookingPaymentSchedule::query()
            ->whereHas('booking', fn (Builder $query) => $query->whereIn('partner_id', $partnerIds))
            ->whereIn('status', ['pending', 'due', 'overdue', 'partially_paid'])
            ->sum('amount');
    }

    /**
     * @param array<int, int> $partnerIds
     * @param array<int, string> $statuses
     */
    private function commissionAmountByStatus(array $partnerIds, array $statuses): float
    {
        return (float) CommissionItem::query()
            ->whereIn('partner_id', $partnerIds)
            ->whereIn('status', $statuses)
            ->sum('commission_amount');
    }

    private function formatMoney(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
