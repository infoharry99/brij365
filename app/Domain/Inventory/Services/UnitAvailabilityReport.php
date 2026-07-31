<?php

namespace App\Domain\Inventory\Services;

use App\Application\Inventory\Data\UnitAvailabilityExportData;
use App\Models\ProjectUnit;
use App\Models\User;
use App\Services\Governance\ManagementReportService;
use App\Services\Governance\ReportLimitPolicy;

final class UnitAvailabilityReport
{
    private const HEADER = 'unit_code,status,company_code,company_name,project_code,project_name,project_city,tower,floor,unit_number,unit_type,carpet_area_sqft,saleable_area_sqft,base_rate,base_price,floor_rise,parking_charges,other_charges,tax_amount,total_price,reserved_until,is_bookable,active_booking_code,active_booking_status,created_at,updated_at';

    public function __construct(
        private readonly InventoryWorkspaceRegister $register,
        private readonly ManagementReportService $reports,
        private readonly ReportLimitPolicy $limits,
    ) {}

    /** @param array<string, mixed> $filters */
    public function build(User $user, array $filters): UnitAvailabilityExportData
    {
        $units = $this->register->unitQuery($user, $filters)
            ->orderBy('project_id')->orderBy('tower')->orderBy('floor')->orderBy('unit_number')
            ->limit($this->limits->maxExportRows())->get();

        $rows = $units->map(fn (ProjectUnit $unit): array => [
            'unit_code' => $unit->unit_code, 'status' => $unit->status,
            'company_code' => $unit->company?->code, 'company_name' => $unit->company?->name,
            'project_code' => $unit->project?->code, 'project_name' => $unit->project?->name, 'project_city' => $unit->project?->city,
            'tower' => $unit->tower, 'floor' => $unit->floor, 'unit_number' => $unit->unit_number, 'unit_type' => $unit->unit_type,
            'carpet_area_sqft' => (float) $unit->carpet_area_sqft, 'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
            'base_rate' => (float) $unit->base_rate, 'base_price' => (float) $unit->base_price, 'floor_rise' => (float) $unit->floor_rise,
            'parking_charges' => (float) $unit->parking_charges, 'other_charges' => (float) $unit->other_charges,
            'tax_amount' => (float) $unit->tax_amount, 'total_price' => (float) $unit->total_price,
            'reserved_until' => $unit->reserved_until?->toDateTimeString(), 'is_bookable' => $unit->isBookable() ? 'yes' : 'no',
            'active_booking_code' => $unit->activeBooking?->booking_code, 'active_booking_status' => $unit->activeBooking?->status,
            'created_at' => $unit->created_at?->toDateTimeString(), 'updated_at' => $unit->updated_at?->toDateTimeString(),
        ])->all();

        return new UnitAvailabilityExportData(
            $rows === [] ? self::HEADER."\n" : $this->reports->csv($rows),
            count($rows),
            'builder360-unit-availability.csv',
        );
    }

    public function maximumRows(): int
    {
        return $this->limits->maxExportRows();
    }
}
