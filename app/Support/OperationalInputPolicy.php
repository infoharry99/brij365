<?php

namespace App\Support;

class OperationalInputPolicy
{
    public function procurementQuantityMax(): string
    {
        return $this->decimalConfig('procurement_quantity_max', '9999999');
    }

    public function constructionQuantityMax(): string
    {
        return $this->decimalConfig('construction_quantity_max', '999999999');
    }

    public function rateMax(): string
    {
        return $this->decimalConfig('rate_max', '999999999');
    }

    public function equipmentHoursMax(): string
    {
        return $this->decimalConfig('equipment_hours_max', '24');
    }

    public function procurementQuantityMaxRule(): string
    {
        return 'max:'.$this->procurementQuantityMax();
    }

    public function constructionQuantityMaxRule(): string
    {
        return 'max:'.$this->constructionQuantityMax();
    }

    public function rateMaxRule(): string
    {
        return 'max:'.$this->rateMax();
    }

    public function equipmentHoursMaxRule(): string
    {
        return 'max:'.$this->equipmentHoursMax();
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $limits = [
            'procurement_quantity_max' => $this->procurementQuantityMax(),
            'construction_quantity_max' => $this->constructionQuantityMax(),
            'rate_max' => $this->rateMax(),
            'equipment_hours_max' => $this->equipmentHoursMax(),
        ];
        $quantityCeiling = $this->decimalConfig('quantity_ceiling', '999999999');
        $rateCeiling = $this->decimalConfig('rate_ceiling', '999999999');
        $equipmentHoursCeiling = $this->decimalConfig('equipment_hours_ceiling', '24');

        $requirements = [
            'all_limits_positive' => collect($limits)->every(fn (string $value): bool => $this->decimalGreaterThan($value, '0')),
            'procurement_quantity_within_quantity_ceiling' => $this->decimalLessThanOrEqual($limits['procurement_quantity_max'], $quantityCeiling),
            'construction_quantity_within_quantity_ceiling' => $this->decimalLessThanOrEqual($limits['construction_quantity_max'], $quantityCeiling),
            'rate_within_rate_ceiling' => $this->decimalLessThanOrEqual($limits['rate_max'], $rateCeiling),
            'equipment_hours_within_daily_ceiling' => $this->decimalLessThanOrEqual($limits['equipment_hours_max'], $equipmentHoursCeiling),
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'limits' => $limits,
            'ceilings' => [
                'quantity_ceiling' => $quantityCeiling,
                'rate_ceiling' => $rateCeiling,
                'equipment_hours_ceiling' => $equipmentHoursCeiling,
            ],
            'requirements' => $requirements,
            'failure' => $ready ? null : $this->failureReason($requirements),
        ];
    }

    private function decimalConfig(string $key, string $default): string
    {
        $value = config('builder360.operational_input_limits.'.$key, $default);

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    private function decimalGreaterThan(string $left, string $right): bool
    {
        return (float) $left > (float) $right;
    }

    private function decimalLessThanOrEqual(string $left, string $right): bool
    {
        return (float) $left <= (float) $right;
    }

    /**
     * @param  array<string, bool>  $requirements
     */
    private function failureReason(array $requirements): ?string
    {
        foreach ($requirements as $requirement => $passed) {
            if (! $passed) {
                return 'operational_input_limits_'.$requirement;
            }
        }

        return null;
    }
}
