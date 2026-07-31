<?php

namespace App\Application\Finance\Actions;

use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\CollectionReceipt;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExportCollectionReceipts
{
    public function __construct(
        private readonly FinanceWorkspaceRegister $register,
        private readonly ManagementReportService $reports,
        private readonly ReportLimitPolicy $limits,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, array $filters, Request $request): Response
    {
        unset($filters['page'], $filters['per_page']);

        $receipts = $this->register->receiptQuery($actor, $filters)
            ->latest('receipt_date')->latest()->limit($this->limits->maxExportRows())->get();

        $rows = $receipts->map(fn (CollectionReceipt $receipt): array => [
            'receipt_number' => $receipt->receipt_number,
            'status' => $receipt->status,
            'receipt_date' => $receipt->receipt_date?->toDateString(),
            'company_code' => $receipt->company?->code,
            'company_name' => $receipt->company?->name,
            'project_code' => $receipt->project?->code,
            'project_name' => $receipt->project?->name,
            'booking_code' => $receipt->booking?->booking_code,
            'milestone' => $receipt->paymentSchedule?->milestone,
            'customer_code' => $receipt->customer?->code,
            'customer_name' => $receipt->customer?->name,
            'customer_email' => $receipt->customer?->email,
            'payment_mode' => $receipt->payment_mode,
            'instrument_number' => $receipt->instrument_number,
            'bank_name' => $receipt->bank_name,
            'amount' => (float) $receipt->amount,
            'tax_deducted_amount' => (float) $receipt->tax_deducted_amount,
            'net_amount' => round((float) $receipt->amount - (float) $receipt->tax_deducted_amount, 2),
            'collected_by' => $receipt->collectedBy?->name,
            'approved_by' => $receipt->approvedBy?->name,
            'approved_at' => $receipt->approved_at?->toDateTimeString(),
            'created_at' => $receipt->created_at?->toDateTimeString(),
        ])->all();

        $this->audit->record($actor, 'finance.collection.exported', 'Exported collection receipt report', null, [
            'format' => 'csv', 'row_count' => count($rows), 'filters' => $filters, 'max_rows' => $this->limits->maxExportRows(),
        ], $request);

        $csv = $rows === []
            ? "receipt_number,status,receipt_date,company_code,company_name,project_code,project_name,booking_code,milestone,customer_code,customer_name,customer_email,payment_mode,instrument_number,bank_name,amount,tax_deducted_amount,net_amount,collected_by,approved_by,approved_at,created_at\n"
            : $this->reports->csv($rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="builder360-collection-receipts.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
