<?php

namespace App\Application\Inventory\Actions;

use App\Application\Inventory\Data\UnitAvailabilityExportData;
use App\Domain\Inventory\Services\UnitAvailabilityReport;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

final class ExportUnitAvailability
{
    public function __construct(private readonly UnitAvailabilityReport $report, private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $filters */
    public function execute(User $user, array $filters, Request $request): UnitAvailabilityExportData
    {
        $export = $this->report->build($user, $filters);
        $this->audit->record($user, 'inventory.unit_availability.exported', 'Exported project unit availability register', null, [
            'format' => 'csv', 'row_count' => $export->rowCount, 'filters' => $filters, 'max_rows' => $this->report->maximumRows(),
        ], $request);

        return $export;
    }
}
