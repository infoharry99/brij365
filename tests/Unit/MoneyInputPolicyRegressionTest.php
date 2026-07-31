<?php

namespace Tests\Unit;

use App\Support\MoneyInputPolicy;
use Illuminate\Support\Facades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class MoneyInputPolicyRegressionTest extends TestCase
{
    public function test_targeted_money_fields_use_central_money_input_policy(): void
    {
        $requestFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Requests'))
        );

        $targetedFiles = [];
        $hardcodedMoneyLimits = [];

        foreach ($requestFiles as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('#/Http/Requests/(Finance|Hr|Recruitment|Payroll|Crm|AfterSales)/#', $path)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);

            if (str_contains($contents, 'MoneyInputPolicy')) {
                $targetedFiles[] = $file->getPathname();
            }

            if (preg_match("/'max:(?:999999999999(?:\\.99)?|9999999999(?:\\.99)?|999999999\\.99|99999999)'/m", $contents) === 1) {
                $hardcodedMoneyLimits[] = $file->getPathname();
            }
        }

        $this->assertGreaterThan(15, count($targetedFiles), 'The regression test should cover finance, HR, recruitment, payroll, CRM and after-sales monetary fields.');
        $this->assertSame([], array_values(array_unique($hardcodedMoneyLimits)), 'Targeted monetary request fields must use MoneyInputPolicy instead of hardcoded high-value ceilings.');
    }

    public function test_money_input_policy_resolves_configured_limits(): void
    {
        Config::set('builder360.money_input_limits.enterprise_amount_max', '1000000.50');
        Config::set('builder360.money_input_limits.payment_amount_max', '900000');
        Config::set('builder360.money_input_limits.hr_amount_max', '800000.25');
        Config::set('builder360.money_input_limits.ctc_amount_max', '700000');
        Config::set('builder360.money_input_limits.maintenance_cost_max', '600000.75');
        Config::set('builder360.money_input_limits.commission_fixed_amount_max', '50000');
        Config::set('builder360.money_input_limits.commission_target_amount_max', '400000');

        $policy = app(MoneyInputPolicy::class);

        $this->assertSame('max:1000000.50', $policy->enterpriseAmountMaxRule());
        $this->assertSame('max:900000', $policy->paymentAmountMaxRule());
        $this->assertSame('max:800000.25', $policy->hrAmountMaxRule());
        $this->assertSame('max:700000', $policy->ctcAmountMaxRule());
        $this->assertSame('max:600000.75', $policy->maintenanceCostMaxRule());
        $this->assertSame('max:50000', $policy->commissionFixedAmountMaxRule());
        $this->assertSame('max:400000', $policy->commissionTargetAmountMaxRule());
    }
}
