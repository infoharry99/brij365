<?php

namespace Tests\Unit;

use App\Support\OperationalInputPolicy;
use Illuminate\Support\Facades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class OperationalInputPolicyRegressionTest extends TestCase
{
    public function test_procurement_and_construction_quantity_limits_use_central_policy(): void
    {
        $requestFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Requests'))
        );

        $targetedFiles = [];
        $hardcodedOperationalLimits = [];

        foreach ($requestFiles as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (! preg_match('#/Http/Requests/(Procurement|Construction)/#', $path)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);

            if (str_contains($contents, 'OperationalInputPolicy')) {
                $targetedFiles[] = $file->getPathname();
            }

            if (preg_match("/'max:(?:9999999|999999999|24)'/m", $contents) === 1) {
                $hardcodedOperationalLimits[] = $file->getPathname();
            }
        }

        $this->assertGreaterThanOrEqual(6, count($targetedFiles), 'The regression test should cover procurement and construction operational quantity/rate fields.');
        $this->assertSame([], array_values(array_unique($hardcodedOperationalLimits)), 'Procurement and construction quantity/rate fields must use OperationalInputPolicy instead of hardcoded ceilings.');
    }

    public function test_operational_input_policy_resolves_configured_limits(): void
    {
        Config::set('builder360.operational_input_limits.procurement_quantity_max', '12345');
        Config::set('builder360.operational_input_limits.construction_quantity_max', '67890.125');
        Config::set('builder360.operational_input_limits.rate_max', '54321.75');
        Config::set('builder360.operational_input_limits.equipment_hours_max', '12');

        $policy = app(OperationalInputPolicy::class);

        $this->assertSame('max:12345', $policy->procurementQuantityMaxRule());
        $this->assertSame('max:67890.125', $policy->constructionQuantityMaxRule());
        $this->assertSame('max:54321.75', $policy->rateMaxRule());
        $this->assertSame('max:12', $policy->equipmentHoursMaxRule());
    }
}
