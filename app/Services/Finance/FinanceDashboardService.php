<?php

namespace App\Services\Finance;

use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\EmployeeLoan;
use App\Models\ExpenseClaim;
use App\Models\FinancialVoucher;
use App\Models\GstEntry;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinanceDashboardService
{
    public function __construct(private readonly CompanyScopeService $companyScope)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $actor, array $filters): array
    {
        $dateTo = isset($filters['date_to'])
            ? CarbonImmutable::parse($filters['date_to'])->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $dateFrom = isset($filters['date_from'])
            ? CarbonImmutable::parse($filters['date_from'])->startOfDay()
            : $dateTo->startOfMonth();

        $forecastDays = (int) ($filters['forecast_days'] ?? 90);
        $forecastFrom = $dateTo->addDay()->startOfDay();
        $forecastTo = $dateTo->addDays($forecastDays)->endOfDay();

        $companyId = isset($filters['company_id']) ? (int) $filters['company_id'] : null;
        $projectId = isset($filters['project_id']) ? (int) $filters['project_id'] : null;

        $cashInAsOf = $this->approvedCollectionsQuery($actor, $companyId, $projectId)
            ->whereDate('receipt_date', '<=', $dateTo->toDateString())
            ->sum('amount');

        $receiptVoucherCashInAsOf = $this->approvedVouchersQuery($actor, $companyId, $projectId)
            ->whereIn('voucher_type', ['receipt', 'contra'])
            ->whereDate('voucher_date', '<=', $dateTo->toDateString())
            ->sum('total_debit');

        $cashOutAsOf = $this->approvedVouchersQuery($actor, $companyId, $projectId)
            ->where('voucher_type', 'payment')
            ->whereDate('voucher_date', '<=', $dateTo->toDateString())
            ->sum('total_credit');

        $periodCollections = $this->approvedCollectionsQuery($actor, $companyId, $projectId)
            ->whereDate('receipt_date', '>=', $dateFrom->toDateString())
            ->whereDate('receipt_date', '<=', $dateTo->toDateString())
            ->sum('amount');

        $periodPaymentVouchers = $this->approvedVouchersQuery($actor, $companyId, $projectId)
            ->where('voucher_type', 'payment')
            ->whereDate('voucher_date', '>=', $dateFrom->toDateString())
            ->whereDate('voucher_date', '<=', $dateTo->toDateString())
            ->sum('total_credit');

        $periodReceiptVouchers = $this->approvedVouchersQuery($actor, $companyId, $projectId)
            ->whereIn('voucher_type', ['receipt', 'contra'])
            ->whereDate('voucher_date', '>=', $dateFrom->toDateString())
            ->whereDate('voucher_date', '<=', $dateTo->toDateString())
            ->sum('total_debit');

        return [
            'period' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'forecast_days' => $forecastDays,
                'company_id' => $companyId,
                'project_id' => $projectId,
            ],
            'cash_position' => [
                'as_of_date' => $dateTo->toDateString(),
                'approved_collection_cash_in' => $this->money($cashInAsOf),
                'approved_receipt_voucher_cash_in' => $this->money($receiptVoucherCashInAsOf),
                'approved_payment_voucher_cash_out' => $this->money($cashOutAsOf),
                'net_cash_position' => $this->money(((float) $cashInAsOf + (float) $receiptVoucherCashInAsOf) - (float) $cashOutAsOf),
            ],
            'period_summary' => [
                'approved_collections' => $this->money($periodCollections),
                'approved_receipt_vouchers' => $this->money($periodReceiptVouchers),
                'approved_payment_vouchers' => $this->money($periodPaymentVouchers),
                'net_period_flow' => $this->money(((float) $periodCollections + (float) $periodReceiptVouchers) - (float) $periodPaymentVouchers),
                'paid_expense_claims' => $this->money($this->expenseClaimsQuery($actor, $companyId)
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$dateFrom, $dateTo])
                    ->sum('approved_amount')),
                'disbursed_employee_loans' => $this->money($this->employeeLoansQuery($actor, $companyId)
                    ->where('status', 'disbursed')
                    ->whereBetween('disbursed_at', [$dateFrom, $dateTo])
                    ->sum('approved_amount')),
            ],
            'receivables' => $this->receivables($actor, $companyId, $projectId, $dateTo, $forecastFrom, $forecastTo),
            'payables' => $this->payables($actor, $companyId, $projectId, $forecastFrom, $forecastTo),
            'gst' => $this->gstSummary($actor, $companyId, $projectId, $dateFrom, $dateTo),
            'approvals' => [
                'submitted_collection_receipts' => $this->collectionsQuery($actor, $companyId, $projectId)->where('status', 'submitted')->count(),
                'submitted_payment_vouchers' => $this->vouchersQuery($actor, $companyId, $projectId)->where('status', 'submitted')->where('voucher_type', 'payment')->count(),
                'submitted_finance_vouchers' => $this->vouchersQuery($actor, $companyId, $projectId)->where('status', 'submitted')->count(),
                'submitted_gst_entries' => $this->gstEntriesQuery($actor, $companyId, $projectId)->where('status', 'submitted')->count(),
                'requested_payment_links' => $this->paymentRequestsQuery($actor, $companyId, $projectId)->where('status', 'requested')->count(),
            ],
            'recent_activity' => [
                'collections' => $this->recentCollections($actor, $companyId, $projectId),
                'vouchers' => $this->recentVouchers($actor, $companyId, $projectId),
                'payment_requests' => $this->recentPaymentRequests($actor, $companyId, $projectId),
            ],
            'source' => 'laravel-sqlite',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receivables(
        User $actor,
        ?int $companyId,
        ?int $projectId,
        CarbonImmutable $asOf,
        CarbonImmutable $forecastFrom,
        CarbonImmutable $forecastTo,
    ): array {
        $schedules = $this->scheduleOutstandingRows($actor, $companyId, $projectId);

        $totalOutstanding = 0.0;
        $overdue = 0.0;
        $dueNextThirty = 0.0;
        $forecastInflow = 0.0;
        $bucketed = [
            'not_due_or_no_due_date' => 0.0,
            'overdue' => 0.0,
            'due_0_30' => 0.0,
            'due_31_60' => 0.0,
            'due_61_plus' => 0.0,
        ];

        foreach ($schedules as $schedule) {
            $outstanding = max((float) $schedule->schedule_amount - (float) $schedule->approved_paid_amount, 0.0);
            if ($outstanding <= 0.0) {
                continue;
            }

            $totalOutstanding += $outstanding;

            if (! $schedule->due_on) {
                $bucketed['not_due_or_no_due_date'] += $outstanding;
                continue;
            }

            $dueOn = CarbonImmutable::parse($schedule->due_on)->startOfDay();
            $daysFromAsOf = $asOf->startOfDay()->diffInDays($dueOn, false);

            if ($dueOn->lt($asOf->startOfDay())) {
                $overdue += $outstanding;
                $bucketed['overdue'] += $outstanding;
            } elseif ($daysFromAsOf <= 30) {
                $dueNextThirty += $outstanding;
                $bucketed['due_0_30'] += $outstanding;
            } elseif ($daysFromAsOf <= 60) {
                $bucketed['due_31_60'] += $outstanding;
            } else {
                $bucketed['due_61_plus'] += $outstanding;
            }

            if ($dueOn->betweenIncluded($forecastFrom->startOfDay(), $forecastTo->startOfDay())) {
                $forecastInflow += $outstanding;
            }
        }

        $requestedPaymentLinks = $this->paymentRequestsQuery($actor, $companyId, $projectId)
            ->where('status', 'requested')
            ->sum('amount');

        return [
            'schedule_outstanding' => $this->money($totalOutstanding),
            'overdue_outstanding' => $this->money($overdue),
            'due_next_30_days' => $this->money($dueNextThirty),
            'requested_payment_links' => $this->money($requestedPaymentLinks),
            'forecast_inflow' => $this->money($forecastInflow),
            'aging_buckets' => array_map(fn (float $amount): float => $this->money($amount), $bucketed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payables(User $actor, ?int $companyId, ?int $projectId, CarbonImmutable $forecastFrom, CarbonImmutable $forecastTo): array
    {
        $submittedPaymentVouchers = $this->vouchersQuery($actor, $companyId, $projectId)
            ->where('status', 'submitted')
            ->where('voucher_type', 'payment')
            ->sum('total_credit');

        $forecastPaymentVouchers = $this->vouchersQuery($actor, $companyId, $projectId)
            ->whereIn('status', ['submitted', 'approved'])
            ->where('voucher_type', 'payment')
            ->whereDate('voucher_date', '>=', $forecastFrom->toDateString())
            ->whereDate('voucher_date', '<=', $forecastTo->toDateString())
            ->sum('total_credit');

        $approvedClaimsNotPaid = $projectId === null
            ? $this->expenseClaimsQuery($actor, $companyId)->where('status', 'approved')->sum('approved_amount')
            : 0;

        $approvedLoansNotDisbursed = $projectId === null
            ? $this->employeeLoansQuery($actor, $companyId)->where('status', 'approved')->sum('approved_amount')
            : 0;

        return [
            'submitted_payment_vouchers' => $this->money($submittedPaymentVouchers),
            'approved_claims_not_paid' => $this->money($approvedClaimsNotPaid),
            'approved_loans_not_disbursed' => $this->money($approvedLoansNotDisbursed),
            'forecast_outflow' => $this->money((float) $forecastPaymentVouchers + (float) $approvedClaimsNotPaid + (float) $approvedLoansNotDisbursed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gstSummary(User $actor, ?int $companyId, ?int $projectId, CarbonImmutable $dateFrom, CarbonImmutable $dateTo): array
    {
        $entries = $this->gstEntriesQuery($actor, $companyId, $projectId)
            ->where('status', 'approved')
            ->whereDate('document_date', '>=', $dateFrom->toDateString())
            ->whereDate('document_date', '<=', $dateTo->toDateString())
            ->selectRaw('transaction_type, COUNT(*) as entry_count, SUM(taxable_amount) as taxable_amount, SUM(total_tax_amount) as total_tax_amount')
            ->groupBy('transaction_type')
            ->get();

        return [
            'approved_entry_count' => (int) $entries->sum('entry_count'),
            'taxable_amount' => $this->money($entries->sum('taxable_amount')),
            'total_tax_amount' => $this->money($entries->sum('total_tax_amount')),
            'by_transaction_type' => $entries->map(fn ($entry): array => [
                'transaction_type' => $entry->transaction_type,
                'entry_count' => (int) $entry->entry_count,
                'taxable_amount' => $this->money($entry->taxable_amount),
                'total_tax_amount' => $this->money($entry->total_tax_amount),
            ])->values()->all(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function scheduleOutstandingRows(User $actor, ?int $companyId, ?int $projectId)
    {
        $paidSubquery = CollectionReceipt::query()
            ->select('booking_payment_schedule_id', DB::raw('SUM(amount) as approved_paid_amount'))
            ->where('status', 'approved')
            ->whereNotNull('booking_payment_schedule_id')
            ->groupBy('booking_payment_schedule_id');

        return BookingPaymentSchedule::query()
            ->join('bookings', 'bookings.id', '=', 'booking_payment_schedules.booking_id')
            ->leftJoinSub($paidSubquery, 'approved_receipts', function ($join): void {
                $join->on('approved_receipts.booking_payment_schedule_id', '=', 'booking_payment_schedules.id');
            })
            ->whereNull('bookings.deleted_at')
            ->when($companyId !== null, fn ($query) => $query->where('bookings.company_id', $companyId))
            ->when($projectId !== null, fn ($query) => $query->where('bookings.project_id', $projectId))
            ->tap(fn ($query) => $this->companyScope->apply($query, $actor, 'bookings.company_id'))
            ->select([
                'booking_payment_schedules.id',
                'booking_payment_schedules.due_on',
                DB::raw('booking_payment_schedules.amount as schedule_amount'),
                DB::raw('COALESCE(approved_receipts.approved_paid_amount, 0) as approved_paid_amount'),
            ])
            ->get();
    }

    private function approvedCollectionsQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->collectionsQuery($actor, $companyId, $projectId)->where('status', 'approved');
    }

    private function collectionsQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->scoped(CollectionReceipt::query(), $actor, $companyId, $projectId);
    }

    private function approvedVouchersQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->vouchersQuery($actor, $companyId, $projectId)->where('status', 'approved');
    }

    private function vouchersQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->scoped(FinancialVoucher::query(), $actor, $companyId, $projectId);
    }

    private function paymentRequestsQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->scoped(PaymentRequest::query(), $actor, $companyId, $projectId);
    }

    private function gstEntriesQuery(User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->scoped(GstEntry::query(), $actor, $companyId, $projectId);
    }

    private function expenseClaimsQuery(User $actor, ?int $companyId): Builder
    {
        return $this->companyScoped(ExpenseClaim::query(), $actor, $companyId);
    }

    private function employeeLoansQuery(User $actor, ?int $companyId): Builder
    {
        return $this->companyScoped(EmployeeLoan::query(), $actor, $companyId);
    }

    private function scoped(Builder $query, User $actor, ?int $companyId, ?int $projectId): Builder
    {
        return $this->companyScoped($query, $actor, $companyId)
            ->when($projectId !== null, fn (Builder $query) => $query->where('project_id', $projectId));
    }

    private function companyScoped(Builder $query, User $actor, ?int $companyId): Builder
    {
        $this->companyScope->apply($query, $actor);

        return $query->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentCollections(User $actor, ?int $companyId, ?int $projectId): array
    {
        return $this->collectionsQuery($actor, $companyId, $projectId)
            ->with(['project:id,code,name', 'customer:id,code,name'])
            ->latest('receipt_date')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CollectionReceipt $receipt): array => [
                'receipt_number' => $receipt->receipt_number,
                'status' => $receipt->status,
                'receipt_date' => $receipt->receipt_date?->toDateString(),
                'amount' => $this->money($receipt->amount),
                'project' => $receipt->project?->code,
                'customer' => $receipt->customer?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentVouchers(User $actor, ?int $companyId, ?int $projectId): array
    {
        return $this->vouchersQuery($actor, $companyId, $projectId)
            ->with('project:id,code,name')
            ->latest('voucher_date')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (FinancialVoucher $voucher): array => [
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'status' => $voucher->status,
                'voucher_date' => $voucher->voucher_date?->toDateString(),
                'amount' => $this->money(max((float) $voucher->total_debit, (float) $voucher->total_credit)),
                'project' => $voucher->project?->code,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPaymentRequests(User $actor, ?int $companyId, ?int $projectId): array
    {
        return $this->paymentRequestsQuery($actor, $companyId, $projectId)
            ->with(['project:id,code,name', 'customer:id,code,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PaymentRequest $request): array => [
                'request_number' => $request->request_number,
                'status' => $request->status,
                'amount' => $this->money($request->amount),
                'expires_at' => $request->expires_at?->toISOString(),
                'project' => $request->project?->code,
                'customer' => $request->customer?->name,
            ])
            ->all();
    }

    private function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }
}
