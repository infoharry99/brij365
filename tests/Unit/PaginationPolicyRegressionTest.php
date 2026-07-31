<?php

namespace Tests\Unit;

use App\Support\PaginationPolicy;
use Illuminate\Support\Facades\Config;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PaginationPolicyRegressionTest extends TestCase
{
    public function test_request_per_page_validation_uses_central_pagination_policy(): void
    {
        $requestFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Http/Requests'))
        );

        $filesWithPerPage = [];
        $hardcodedPerPageRules = [];
        $missingPolicyImports = [];

        foreach ($requestFiles as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);

            if (! str_contains($contents, "'per_page'")) {
                continue;
            }

            $filesWithPerPage[] = $file->getPathname();

            if (preg_match("/'per_page'\\s*=>\\s*\\[[^\\]]*'max:\\d+'/m", $contents) === 1) {
                $hardcodedPerPageRules[] = $file->getPathname();
            }

            if (! str_contains($contents, PaginationPolicy::class)) {
                $missingPolicyImports[] = $file->getPathname();
            }
        }

        $this->assertGreaterThan(40, count($filesWithPerPage), 'The regression test should cover the request-layer pagination surface.');
        $this->assertSame([], $hardcodedPerPageRules, 'Request per_page validation must use PaginationPolicy instead of hardcoded max rules.');
        $this->assertSame([], $missingPolicyImports, 'Request classes with per_page validation must reference PaginationPolicy.');
    }

    public function test_production_pagination_defaults_use_central_pagination_policy(): void
    {
        $productionFiles = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path())
        );

        $filesWithPagination = [];
        $hardcodedPaginationDefaults = [];
        $missingPolicyReferences = [];

        foreach ($productionFiles as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            $this->assertIsString($contents);

            if (! str_contains($contents, '->paginate(')) {
                continue;
            }

            $filesWithPagination[] = $file->getPathname();

            if (preg_match('/->paginate\\((?:15|25|50|100)\\)/m', $contents) === 1) {
                $hardcodedPaginationDefaults[] = $file->getPathname();
            }

            if (preg_match("/->paginate\\([^)]*\\?\\?\\s*(?:15|25|50|100)/m", $contents) === 1) {
                $hardcodedPaginationDefaults[] = $file->getPathname();
            }

            $isApplicationOrchestration = str_contains(str_replace('\\', '/', $file->getPathname()), '/app/Application/');
            $acceptsPolicyResolvedPageSize = str_contains($contents, 'int $perPage');

            if (! str_contains($contents, PaginationPolicy::class) && ! $isApplicationOrchestration && ! $acceptsPolicyResolvedPageSize) {
                $missingPolicyReferences[] = $file->getPathname();
            }
        }

        $this->assertGreaterThan(30, count($filesWithPagination), 'The regression test should cover production pagination calls across controllers and domain registers.');
        $this->assertSame([], array_values(array_unique($hardcodedPaginationDefaults)), 'Production paginate calls must use PaginationPolicy instead of hardcoded fallback sizes.');
        $this->assertSame([], $missingPolicyReferences, 'Production classes with paginate calls must reference PaginationPolicy.');
    }

    public function test_pagination_policy_resolves_configured_fallback_page_sizes_and_caps_requests(): void
    {
        Config::set('builder360.pagination.default_per_page', 12);
        Config::set('builder360.pagination.workspace_per_page', 24);
        Config::set('builder360.pagination.large_per_page', 48);
        Config::set('builder360.pagination.default_max_per_page', 50);
        Config::set('builder360.pagination.large_max_per_page', 100);

        $policy = app(PaginationPolicy::class);

        $this->assertSame(12, $policy->defaultPerPage());
        $this->assertSame(24, $policy->workspacePerPage());
        $this->assertSame(48, $policy->largePerPage());
        $this->assertSame(7, $policy->defaultPerPage(7));
        $this->assertSame(50, $policy->defaultPerPage(75));
        $this->assertSame(100, $policy->largePerPage(125));
    }
}
