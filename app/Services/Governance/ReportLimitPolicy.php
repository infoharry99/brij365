<?php

namespace App\Services\Governance;

class ReportLimitPolicy
{
    public function maxDateRangeDays(): int
    {
        return max(1, (int) config('builder360.reports.max_date_range_days', 366));
    }

    public function maxExportRows(): int
    {
        return max(1, (int) config('builder360.reports.max_export_rows', 500));
    }

    public function maxExportRowsCeiling(): int
    {
        return max(1, (int) config('builder360.reports.max_export_rows_ceiling', 5000));
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $maxDateRangeDays = $this->maxDateRangeDays();
        $maxExportRows = $this->maxExportRows();
        $maxExportRowsCeiling = $this->maxExportRowsCeiling();
        $requirements = [
            'date_range_positive' => $maxDateRangeDays > 0,
            'date_range_within_operational_year' => $maxDateRangeDays <= 366,
            'export_row_limit_positive' => $maxExportRows > 0,
            'export_row_limit_within_ceiling' => $maxExportRows <= $maxExportRowsCeiling,
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'max_date_range_days' => $maxDateRangeDays,
            'max_export_rows' => $maxExportRows,
            'max_export_rows_ceiling' => $maxExportRowsCeiling,
            'requirements' => $requirements,
            'failure' => $ready ? null : $this->failureReason($requirements),
        ];
    }

    /**
     * @param  array<string, bool>  $requirements
     */
    private function failureReason(array $requirements): ?string
    {
        foreach ($requirements as $requirement => $passed) {
            if (! $passed) {
                return 'report_limits_'.$requirement;
            }
        }

        return null;
    }
}
