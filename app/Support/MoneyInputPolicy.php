<?php

namespace App\Support;

class MoneyInputPolicy
{
    public function enterpriseAmountMax(): string
    {
        return $this->decimalConfig('enterprise_amount_max', '999999999999.99');
    }

    public function paymentAmountMax(): string
    {
        return $this->decimalConfig('payment_amount_max', '999999999999');
    }

    public function hrAmountMax(): string
    {
        return $this->decimalConfig('hr_amount_max', '999999999.99');
    }

    public function ctcAmountMax(): string
    {
        return $this->decimalConfig('ctc_amount_max', '9999999999');
    }

    public function maintenanceCostMax(): string
    {
        return $this->decimalConfig('maintenance_cost_max', '9999999999.99');
    }

    public function commissionFixedAmountMax(): string
    {
        return $this->decimalConfig('commission_fixed_amount_max', '99999999');
    }

    public function commissionTargetAmountMax(): string
    {
        return $this->decimalConfig('commission_target_amount_max', '9999999999');
    }

    public function enterpriseAmountMaxRule(): string
    {
        return 'max:'.$this->enterpriseAmountMax();
    }

    public function paymentAmountMaxRule(): string
    {
        return 'max:'.$this->paymentAmountMax();
    }

    public function hrAmountMaxRule(): string
    {
        return 'max:'.$this->hrAmountMax();
    }

    public function ctcAmountMaxRule(): string
    {
        return 'max:'.$this->ctcAmountMax();
    }

    public function maintenanceCostMaxRule(): string
    {
        return 'max:'.$this->maintenanceCostMax();
    }

    public function commissionFixedAmountMaxRule(): string
    {
        return 'max:'.$this->commissionFixedAmountMax();
    }

    public function commissionTargetAmountMaxRule(): string
    {
        return 'max:'.$this->commissionTargetAmountMax();
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $limits = [
            'enterprise_amount_max' => $this->enterpriseAmountMax(),
            'payment_amount_max' => $this->paymentAmountMax(),
            'hr_amount_max' => $this->hrAmountMax(),
            'ctc_amount_max' => $this->ctcAmountMax(),
            'maintenance_cost_max' => $this->maintenanceCostMax(),
            'commission_fixed_amount_max' => $this->commissionFixedAmountMax(),
            'commission_target_amount_max' => $this->commissionTargetAmountMax(),
        ];
        $enterpriseCeiling = $this->decimalConfig('enterprise_amount_ceiling', '999999999999.99');
        $hrCeiling = $this->decimalConfig('hr_amount_ceiling', '999999999.99');
        $ctcCeiling = $this->decimalConfig('ctc_amount_ceiling', '9999999999');

        $requirements = [
            'all_limits_positive' => collect($limits)->every(fn (string $value): bool => $this->decimalGreaterThan($value, '0')),
            'payment_within_enterprise_ceiling' => $this->decimalLessThanOrEqual($limits['payment_amount_max'], $enterpriseCeiling),
            'enterprise_within_enterprise_ceiling' => $this->decimalLessThanOrEqual($limits['enterprise_amount_max'], $enterpriseCeiling),
            'hr_within_hr_ceiling' => $this->decimalLessThanOrEqual($limits['hr_amount_max'], $hrCeiling),
            'maintenance_within_enterprise_ceiling' => $this->decimalLessThanOrEqual($limits['maintenance_cost_max'], $enterpriseCeiling),
            'ctc_within_ctc_ceiling' => $this->decimalLessThanOrEqual($limits['ctc_amount_max'], $ctcCeiling),
            'commission_fixed_within_hr_ceiling' => $this->decimalLessThanOrEqual($limits['commission_fixed_amount_max'], $hrCeiling),
            'commission_target_within_enterprise_ceiling' => $this->decimalLessThanOrEqual($limits['commission_target_amount_max'], $enterpriseCeiling),
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'limits' => $limits,
            'ceilings' => [
                'enterprise_amount_ceiling' => $enterpriseCeiling,
                'hr_amount_ceiling' => $hrCeiling,
                'ctc_amount_ceiling' => $ctcCeiling,
            ],
            'requirements' => $requirements,
            'failure' => $ready ? null : $this->failureReason($requirements),
        ];
    }

    private function decimalConfig(string $key, string $default): string
    {
        $value = config('builder360.money_input_limits.'.$key, $default);

        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
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
                return 'money_input_limits_'.$requirement;
            }
        }

        return null;
    }
}
