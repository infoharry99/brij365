<?php

namespace App\Services\Payroll;

class PayrollRunControlPolicy
{
    public function minPeriodYear(): int
    {
        return (int) config('builder360.payroll.period_year_min', 2020);
    }

    public function maxPeriodYear(): int
    {
        return (int) config('builder360.payroll.period_year_max', 2100);
    }

    public function minWorkingDays(): int
    {
        return max(1, (int) config('builder360.payroll.working_days_min', 1));
    }

    public function maxWorkingDays(): int
    {
        return max(1, (int) config('builder360.payroll.working_days_max', 31));
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $minYear = $this->minPeriodYear();
        $maxYear = $this->maxPeriodYear();
        $minWorkingDays = $this->minWorkingDays();
        $maxWorkingDays = $this->maxWorkingDays();

        $requirements = [
            'period_min_year_reasonable' => $minYear >= 2000,
            'period_max_year_after_min_year' => $maxYear >= $minYear,
            'period_window_not_unbounded' => ($maxYear - $minYear) <= 150,
            'working_days_min_positive' => $minWorkingDays >= 1,
            'working_days_max_at_least_min' => $maxWorkingDays >= $minWorkingDays,
            'working_days_max_calendar_safe' => $maxWorkingDays <= 31,
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'period_year_min' => $minYear,
            'period_year_max' => $maxYear,
            'working_days_min' => $minWorkingDays,
            'working_days_max' => $maxWorkingDays,
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
                return 'payroll_controls_'.$requirement;
            }
        }

        return null;
    }
}
