<?php

namespace App\Services\Governance;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\CollectionReceipt;
use App\Models\ConstructionMilestone;
use App\Models\DailyProgressReport;
use App\Models\Lead;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\ProjectUnit;
use App\Models\PurchaseOrder;
use App\Models\ReraRegistration;
use App\Models\ServiceTicket;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManagementReportService
{
    public function __construct(
        private readonly CompanyScopeService $companyScope,
        private readonly ReportLimitPolicy $reportLimitPolicy,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $companyId = $this->companyScope($user);

        return [
            'scope' => [
                'company_id' => $companyId,
                'generated_at' => now()->toISOString(),
            ],
            'crm' => [
                'open_leads' => $this->companyQuery(Lead::query(), $companyId)->where('status', 'open')->count(),
                'won_leads' => $this->companyQuery(Lead::query(), $companyId)->where('status', 'won')->count(),
                'pipeline_value' => (float) $this->companyQuery(Lead::query(), $companyId)->where('status', 'open')->sum('expected_value'),
                'by_stage' => $this->groupCountAndSum($this->companyQuery(Lead::query(), $companyId), 'stage', 'expected_value'),
            ],
            'sales' => [
                'confirmed_bookings' => $this->companyQuery(Booking::query(), $companyId)->where('status', 'confirmed')->count(),
                'net_receivable' => (float) $this->companyQuery(Booking::query(), $companyId)->where('status', 'confirmed')->sum('net_receivable'),
                'by_status' => $this->groupCount($this->companyQuery(Booking::query(), $companyId), 'status'),
            ],
            'collections' => [
                'approved_amount' => (float) $this->companyQuery(CollectionReceipt::query(), $companyId)->where('status', 'approved')->sum('amount'),
                'submitted_amount' => (float) $this->companyQuery(CollectionReceipt::query(), $companyId)->where('status', 'submitted')->sum('amount'),
                'by_status' => $this->groupCountAndSum($this->companyQuery(CollectionReceipt::query(), $companyId), 'status', 'amount'),
            ],
            'inventory' => [
                'total_units' => $this->companyQuery(ProjectUnit::query(), $companyId)->count(),
                'by_status' => $this->groupCount($this->companyQuery(ProjectUnit::query(), $companyId), 'status'),
            ],
            'construction' => [
                'active_projects' => $this->companyQuery(Project::query(), $companyId)->where('status', 'active')->count(),
                'milestones_by_status' => $this->groupCount($this->companyQuery(ConstructionMilestone::query(), $companyId), 'status'),
            ],
            'payroll' => [
                'runs_by_status' => $this->groupCount($this->companyQuery(PayrollRun::query(), $companyId), 'status'),
                'approved_net_payable' => (float) $this->companyQuery(PayrollRun::query(), $companyId)->where('status', 'approved')->sum('net_payable'),
            ],
            'after_sales' => [
                'open_tickets' => $this->companyQuery(ServiceTicket::query(), $companyId)->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
                'overdue_tickets' => $this->companyQuery(ServiceTicket::query(), $companyId)
                    ->whereIn('status', ['open', 'assigned', 'in_progress'])
                    ->where('sla_due_at', '<', now())
                    ->count(),
                'by_status' => $this->groupCount($this->companyQuery(ServiceTicket::query(), $companyId), 'status'),
            ],
            'audit' => [
                'events_last_7_days' => $this->auditQuery($user)->where('created_at', '>=', now()->subDays(7))->count(),
                'by_event_type' => $this->auditQuery($user)
                    ->select('event_type', DB::raw('count(*) as event_count'))
                    ->groupBy('event_type')
                    ->orderByDesc('event_count')
                    ->limit(10)
                    ->get()
                    ->map(fn ($row): array => ['event_type' => $row->event_type, 'count' => (int) $row->event_count])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<int, array<string, mixed>>
     */
    public function summaryRows(array $summary): array
    {
        $scope = $summary['scope'] ?? [];
        $rows = [];

        foreach ($summary as $section => $metrics) {
            if ($section === 'scope' || ! is_array($metrics)) {
                continue;
            }

            foreach ($metrics as $metric => $value) {
                $rows[] = [
                    'section' => $section,
                    'metric' => $metric,
                    'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
                    'company_id' => $scope['company_id'] ?? null,
                    'generated_at' => $scope['generated_at'] ?? now()->toISOString(),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function register(User $user, array $filters): array
    {
        $report = $filters['report'] ?? 'bookings';

        return match ($report) {
            'collections' => $this->collectionRows($user, $filters),
            'payroll' => $this->payrollRows($user, $filters),
            'service_tickets' => $this->serviceTicketRows($user, $filters),
            'leads' => $this->leadRows($user, $filters),
            'inventory_units' => $this->inventoryUnitRows($user, $filters),
            'stock_items' => $this->stockItemRows($user, $filters),
            'stock_movements' => $this->stockMovementRows($user, $filters),
            'purchase_orders' => $this->purchaseOrderRows($user, $filters),
            'vendors' => $this->vendorRows($user, $filters),
            'construction_milestones' => $this->constructionMilestoneRows($user, $filters),
            'daily_progress_reports' => $this->dailyProgressReportRows($user, $filters),
            'rera_registrations' => $this->reraRegistrationRows($user, $filters),
            'audit_events' => $this->auditEventRows($user, $filters),
            default => $this->bookingRows($user, $filters),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function csv(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_keys($rows[0]));

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value): string => $this->csvCell($value), $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Build an Excel-compatible XML Spreadsheet 2003 workbook.
     *
     * This avoids adding a binary XLSX dependency while still opening cleanly in
     * Excel, LibreOffice and Google Sheets import workflows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function excelXml(array $rows, string $worksheetName = 'Report'): string
    {
        $worksheetName = $this->excelWorksheetName($worksheetName);
        $headers = $rows === [] ? [] : array_keys($rows[0]);

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<?mso-application progid="Excel.Sheet"?>',
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">',
            '<Worksheet ss:Name="'.$this->xml($worksheetName).'">',
            '<Table>',
        ];

        if ($headers !== []) {
            $xml[] = '<Row>';
            foreach ($headers as $header) {
                $xml[] = '<Cell><Data ss:Type="String">'.$this->xml($header).'</Data></Cell>';
            }
            $xml[] = '</Row>';
        }

        foreach ($rows as $row) {
            $xml[] = '<Row>';
            foreach ($headers as $header) {
                $xml[] = '<Cell><Data ss:Type="String">'.$this->xml($this->spreadsheetCell($row[$header] ?? null)).'</Data></Cell>';
            }
            $xml[] = '</Row>';
        }

        $xml[] = '</Table>';
        $xml[] = '</Worksheet>';
        $xml[] = '</Workbook>';

        return implode('', $xml);
    }

    /**
     * Build a simple dependency-free PDF report.
     *
     * The output is intentionally text-only so report exports remain available
     * without introducing a PDF rendering package into the current stack.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function pdf(array $rows, string $title = 'Builder360 Report'): string
    {
        $lines = $this->pdfLines($rows, $title);
        $pages = array_chunk($lines, 46);
        $pages = $pages === [] ? [[]] : $pages;

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $fontObjectNumber = 3;
        $pageObjectNumbers = [];
        $nextObject = 4;

        foreach ($pages as $pageLines) {
            $pageObjectNumber = $nextObject++;
            $contentObjectNumber = $nextObject++;
            $pageObjectNumbers[] = $pageObjectNumber;

            $content = $this->pdfContentStream($pageLines);
            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontObjectNumber.' 0 R >> >> /Contents '.$contentObjectNumber.' 0 R >>';
            $objects[$contentObjectNumber] = "<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $objectNumber): string => $objectNumber.' 0 R', $pageObjectNumbers)).'] /Count '.count($pageObjectNumbers).' >>';
        $objects[$fontObjectNumber] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $objectNumber => $body) {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= $objectNumber." 0 obj\n".$body."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    private function csvCell(mixed $value): string
    {
        return $this->spreadsheetCell($value);
    }

    private function spreadsheetCell(mixed $value): string
    {
        $cell = $this->scalarCell($value);

        return preg_match('/^\s*[=+\-@\t\r]/', $cell) === 1 ? "'".$cell : $cell;
    }

    private function scalarCell(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function excelWorksheetName(string $name): string
    {
        $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $name) ?? 'Report';
        $name = trim($name);

        return mb_substr($name !== '' ? $name : 'Report', 0, 31);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function pdfLines(array $rows, string $title): array
    {
        $lines = [
            mb_substr($title, 0, 100),
            'Generated at: '.now()->toDateTimeString(),
            'Rows: '.count($rows),
            '',
        ];

        if ($rows === []) {
            $lines[] = 'No records found for the selected filters.';

            return $lines;
        }

        $headers = array_keys($rows[0]);
        $lines[] = implode(' | ', $headers);
        $lines[] = str_repeat('-', min(120, strlen($lines[array_key_last($lines)])));

        foreach ($rows as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = mb_substr($this->scalarCell($row[$header] ?? null), 0, 40);
            }

            $lines[] = mb_substr(implode(' | ', $values), 0, 130);
        }

        return $lines;
    }

    /**
     * @param array<int, string> $lines
     */
    private function pdfContentStream(array $lines): string
    {
        $content = "BT\n/F1 9 Tf\n36 806 Td\n14 TL\n";

        foreach ($lines as $line) {
            $content .= '('.$this->pdfText($line).") Tj\nT*\n";
        }

        return $content."ET";
    }

    private function pdfText(string $value): string
    {
        $value = preg_replace('/[^\P{C}\t\r\n]+/u', '', $value) ?? '';

        return str_replace(['\\', '(', ')', "\r", "\n", "\t"], ['\\\\', '\(', '\)', ' ', ' ', ' '], $value);
    }

    public function auditQuery(User $user): Builder
    {
        $companyId = $this->companyScope($user);

        return AuditEvent::query()
            ->with('user.role')
            ->when($companyId !== null, function (Builder $query) use ($companyId): void {
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery->where('company_id', $companyId));
            });
    }

    /**
     * @param Builder $query
     */
    private function companyQuery(Builder $query, ?int $companyId): Builder
    {
        return $query->when($companyId !== null, fn (Builder $scopedQuery) => $scopedQuery->where('company_id', $companyId));
    }

    private function companyScope(User $user): ?int
    {
        return $this->companyScope->companyIdFor($user);
    }

    /**
     * @return array<int, array{key: string, count: int}>
     */
    private function groupCount(Builder $query, string $column): array
    {
        return $query
            ->select($column, DB::raw('count(*) as row_count'))
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->map(fn ($row): array => ['key' => (string) $row->{$column}, 'count' => (int) $row->row_count])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, count: int, amount: float}>
     */
    private function groupCountAndSum(Builder $query, string $column, string $sumColumn): array
    {
        return $query
            ->select($column, DB::raw('count(*) as row_count'), DB::raw("sum({$sumColumn}) as row_sum"))
            ->groupBy($column)
            ->orderBy($column)
            ->get()
            ->map(fn ($row): array => ['key' => (string) $row->{$column}, 'count' => (int) $row->row_count, 'amount' => (float) $row->row_sum])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function bookingRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(Booking::query(), $this->companyScope($user)), $filters, 'booked_on')
            ->with(['project', 'unit', 'customer'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('booked_on')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (Booking $booking): array => [
                'booking_code' => $booking->booking_code,
                'status' => $booking->status,
                'booked_on' => $booking->booked_on?->toDateString(),
                'project' => $booking->project?->code,
                'unit' => $booking->unit?->unit_code,
                'customer' => $booking->customer?->name,
                'net_receivable' => (float) $booking->net_receivable,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function collectionRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(CollectionReceipt::query(), $this->companyScope($user)), $filters, 'receipt_date')
            ->with(['booking', 'customer'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('receipt_date')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (CollectionReceipt $receipt): array => [
                'receipt_number' => $receipt->receipt_number,
                'status' => $receipt->status,
                'receipt_date' => $receipt->receipt_date?->toDateString(),
                'booking' => $receipt->booking?->booking_code,
                'customer' => $receipt->customer?->name,
                'payment_mode' => $receipt->payment_mode,
                'amount' => (float) $receipt->amount,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function payrollRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(PayrollRun::query(), $this->companyScope($user)), $filters, 'period_start')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (PayrollRun $run): array => [
                'run_number' => $run->run_number,
                'status' => $run->status,
                'period' => sprintf('%04d-%02d', $run->period_year, $run->period_month),
                'gross_earnings' => (float) $run->gross_earnings,
                'total_deductions' => (float) $run->total_deductions,
                'net_payable' => (float) $run->net_payable,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function serviceTicketRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(ServiceTicket::query(), $this->companyScope($user)), $filters, 'created_at')
            ->with(['booking', 'customer', 'assignedTo'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('created_at')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (ServiceTicket $ticket): array => [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'booking' => $ticket->booking?->booking_code,
                'customer' => $ticket->customer?->name,
                'assigned_to' => $ticket->assignedTo?->name,
                'sla_due_at' => $ticket->sla_due_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function leadRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(Lead::query(), $this->companyScope($user)), $filters, 'created_at')
            ->with(['project', 'customer', 'owner', 'marketingCampaign'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('created_at')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (Lead $lead): array => [
                'lead_code' => $lead->lead_code,
                'status' => $lead->status,
                'stage' => $lead->stage,
                'source' => $lead->source,
                'project' => $lead->project?->code,
                'customer' => $lead->customer?->name,
                'owner' => $lead->owner?->name,
                'campaign' => $lead->marketingCampaign?->campaign_code,
                'expected_value' => (float) $lead->expected_value,
                'follow_up_at' => $lead->follow_up_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function inventoryUnitRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(ProjectUnit::query(), $this->companyScope($user)), $filters, 'created_at')
            ->with(['project', 'activeBooking.customer'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderBy('project_id')
            ->orderBy('tower')
            ->orderBy('floor')
            ->orderBy('unit_number')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (ProjectUnit $unit): array => [
                'unit_code' => $unit->unit_code,
                'status' => $unit->status,
                'project' => $unit->project?->code,
                'tower' => $unit->tower,
                'floor' => $unit->floor,
                'unit_number' => $unit->unit_number,
                'unit_type' => $unit->unit_type,
                'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
                'total_price' => (float) $unit->total_price,
                'booking_code' => $unit->activeBooking?->booking_code,
                'customer' => $unit->activeBooking?->customer?->name,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function stockItemRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(StockItem::query(), $this->companyScope($user)), $filters, 'last_movement_at')
            ->with('project')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderBy('project_id')
            ->orderBy('store_type')
            ->orderBy('item_code')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (StockItem $item): array => [
                'item_code' => $item->item_code,
                'description' => $item->description,
                'project' => $item->project?->code,
                'store_type' => $item->store_type,
                'unit' => $item->unit,
                'status' => $item->status,
                'on_hand_quantity' => (float) $item->on_hand_quantity,
                'minimum_stock_quantity' => (float) $item->minimum_stock_quantity,
                'average_rate' => (float) $item->average_rate,
                'stock_value' => (float) $item->stock_value,
                'below_minimum' => $item->isBelowMinimum() ? 'yes' : 'no',
                'last_movement_at' => $item->last_movement_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function stockMovementRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(StockMovement::query(), $this->companyScope($user)), $filters, 'movement_date')
            ->with(['project', 'purchaseOrder', 'createdBy'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('movement_type', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (StockMovement $movement): array => [
                'movement_number' => $movement->movement_number,
                'movement_type' => $movement->movement_type,
                'movement_date' => $movement->movement_date?->toDateString(),
                'project' => $movement->project?->code,
                'item_code' => $movement->item_code,
                'description' => $movement->description,
                'store_type' => $movement->store_type,
                'unit' => $movement->unit,
                'quantity' => (float) $movement->quantity,
                'rate' => (float) $movement->rate,
                'amount' => (float) $movement->amount,
                'balance_after_quantity' => (float) $movement->balance_after_quantity,
                'balance_after_value' => (float) $movement->balance_after_value,
                'purchase_order' => $movement->purchaseOrder?->po_number,
                'created_by' => $movement->createdBy?->name,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function purchaseOrderRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(PurchaseOrder::query(), $this->companyScope($user)), $filters, 'po_date')
            ->with(['project', 'vendor', 'approvedBy'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('po_date')
            ->orderByDesc('id')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'po_number' => $order->po_number,
                'status' => $order->status,
                'po_date' => $order->po_date?->toDateString(),
                'expected_delivery_on' => $order->expected_delivery_on?->toDateString(),
                'project' => $order->project?->code,
                'vendor_code' => $order->vendor?->vendor_code,
                'vendor' => $order->vendor?->name,
                'subtotal' => (float) $order->subtotal,
                'tax_amount' => (float) $order->tax_amount,
                'total_amount' => (float) $order->total_amount,
                'approved_by' => $order->approvedBy?->name,
                'approved_at' => $order->approved_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function vendorRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(Vendor::query(), $this->companyScope($user)), $filters, 'created_at')
            ->withCount('purchaseOrders')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('vendor_code')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (Vendor $vendor): array => [
                'vendor_code' => $vendor->vendor_code,
                'name' => $vendor->name,
                'vendor_type' => $vendor->vendor_type,
                'status' => $vendor->status,
                'contact_name' => $vendor->contact_name,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'gstin' => $vendor->gstin,
                'pan_last4' => $vendor->pan_last4,
                'purchase_orders_count' => (int) $vendor->purchase_orders_count,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function constructionMilestoneRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(ConstructionMilestone::query(), $this->companyScope($user)), $filters, 'planned_start_on')
            ->with(['project', 'createdBy'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderBy('planned_start_on')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (ConstructionMilestone $milestone): array => [
                'milestone_code' => $milestone->milestone_code,
                'name' => $milestone->name,
                'project' => $milestone->project?->code,
                'phase' => $milestone->phase,
                'status' => $milestone->status,
                'planned_start_on' => $milestone->planned_start_on?->toDateString(),
                'planned_end_on' => $milestone->planned_end_on?->toDateString(),
                'actual_start_on' => $milestone->actual_start_on?->toDateString(),
                'actual_end_on' => $milestone->actual_end_on?->toDateString(),
                'weight_percent' => (float) $milestone->weight_percent,
                'progress_percent' => (float) $milestone->progress_percent,
                'created_by' => $milestone->createdBy?->name,
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function dailyProgressReportRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(DailyProgressReport::query(), $this->companyScope($user)), $filters, 'report_date')
            ->with(['project', 'preparedBy', 'approvedBy'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByDesc('report_date')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(function (DailyProgressReport $report): array {
                $progressItems = collect($report->progress_items ?? []);
                $materialItems = collect($report->materials_used ?? []);
                $equipmentItems = collect($report->equipment_used ?? []);
                $blockerItems = collect($report->blockers ?? []);

                return [
                    'report_number' => $report->report_number,
                    'report_date' => $report->report_date?->toDateString(),
                    'project' => $report->project?->code,
                    'status' => $report->status,
                    'weather' => $report->weather,
                    'manpower_count' => (int) $report->manpower_count,
                    'progress_item_count' => $progressItems->count(),
                    'average_completion_percent' => round((float) $progressItems->avg(fn ($item): float => (float) ($item['completion_percent'] ?? $item['progress_percent'] ?? 0)), 2),
                    'materials_used_count' => $materialItems->count(),
                    'equipment_used_count' => $equipmentItems->count(),
                    'open_blocker_count' => $blockerItems->count(),
                    'work_summary' => $report->work_summary,
                    'safety_observations' => $report->safety_observations,
                    'quality_observations' => $report->quality_observations,
                    'prepared_by' => $report->preparedBy?->name,
                    'approved_by' => $report->approvedBy?->name,
                    'approved_at' => $report->approved_at?->toDateTimeString(),
                ];
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function reraRegistrationRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->companyQuery(ReraRegistration::query(), $this->companyScope($user)), $filters, 'registered_on')
            ->with(['project', 'createdBy', 'verifiedBy'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['project_id']), fn (Builder $query) => $query->where('project_id', $filters['project_id']))
            ->orderByRaw('case when expires_on is null then 1 else 0 end')
            ->orderBy('expires_on')
            ->orderByDesc('registered_on')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(function (ReraRegistration $registration): array {
                $daysToExpiry = $registration->expires_on
                    ? (int) now()->startOfDay()->diffInDays($registration->expires_on->copy()->startOfDay(), false)
                    : null;

                return [
                    'registration_number' => $registration->registration_number,
                    'project' => $registration->project?->code,
                    'authority_name' => $registration->authority_name,
                    'state_code' => $registration->state_code,
                    'status' => $registration->status,
                    'registered_on' => $registration->registered_on?->toDateString(),
                    'expires_on' => $registration->expires_on?->toDateString(),
                    'days_to_expiry' => $daysToExpiry,
                    'document_reference' => $registration->document_reference,
                    'condition_count' => count($registration->conditions ?? []),
                    'created_by' => $registration->createdBy?->name,
                    'verified_by' => $registration->verifiedBy?->name,
                    'verified_at' => $registration->verified_at?->toDateTimeString(),
                ];
            })
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function auditEventRows(User $user, array $filters): array
    {
        return $this->dateFiltered($this->auditQuery($user), $filters, 'created_at')
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('event_type', $filters['status']))
            ->orderByDesc('created_at')
            ->limit($this->reportLimitPolicy->maxExportRows())
            ->get()
            ->map(fn (AuditEvent $event): array => [
                'event_type' => $event->event_type,
                'action' => $event->action,
                'user' => $event->user?->name,
                'role' => $event->user?->role?->name,
                'auditable_type' => $event->auditable_type,
                'auditable_id' => $event->auditable_id,
                'request_method' => $event->request_method,
                'request_path' => $event->request_path,
                'request_id' => $event->request_id,
                'created_at' => $event->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function dateFiltered(Builder $query, array $filters, string $column): Builder
    {
        return $query
            ->when(isset($filters['date_from']), fn (Builder $dateQuery) => $dateQuery->whereDate($column, '>=', $filters['date_from']))
            ->when(isset($filters['date_to']), fn (Builder $dateQuery) => $dateQuery->whereDate($column, '<=', $filters['date_to']));
    }
}
