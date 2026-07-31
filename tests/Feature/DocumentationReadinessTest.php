<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class DocumentationReadinessTest extends TestCase
{
    public function test_readme_production_boundary_matches_current_laravel_capabilities(): void
    {
        $readme = file_get_contents(base_path('README.md'));

        $this->assertIsString($readme);
        $this->assertStringContainsString('## Production Readiness Boundary', $readme);
        $this->assertStringContainsString('password reset, email verification, account-status enforcement', $readme);
        $this->assertStringContainsString('role-aware dashboard data', $readme);
        $this->assertStringContainsString('managed-document download controls', $readme);
        $this->assertStringContainsString('php artisan migrate --force', $readme);
        $this->assertStringContainsString('/operations/readiness', $readme);
        $this->assertStringContainsString('All active browser pages use server-rendered Blade workspaces', $readme);
        $this->assertStringContainsString('npm run build', $readme);
        $this->assertStringContainsString('Vite production assets', $readme);
        $this->assertStringContainsString('php artisan builder360:reconcile --json', $readme);
        $this->assertStringNotContainsString('legacy React prototype screens', $readme);

        $this->assertStringNotContainsString('## Production Hardening Still Required', $readme);
        $this->assertStringNotContainsString('Password reset, email verification and account lifecycle screens', $readme);
        $this->assertStringNotContainsString('File storage and document permissions', $readme);
    }

    public function test_client_facing_blade_navigation_uses_business_labels_not_api_jargon(): void
    {
        $clientFacingViews = [
            'resources/views/buyer/summary.blade.php',
            'resources/views/partner/summary.blade.php',
            'resources/views/notifications/index.blade.php',
            'resources/views/documents/managed-documents/index.blade.php',
        ];

        foreach ($clientFacingViews as $view) {
            $contents = file_get_contents(base_path($view));

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('>Bookings JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Receipts JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Documents JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Tickets JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Leads JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Summary JSON<', $contents, "{$view} should not expose API wording in navigation.");
            $this->assertStringNotContainsString('>Categories JSON<', $contents, "{$view} should not expose API wording in navigation.");
        }
    }

    public function test_sow_completion_handover_artifacts_are_present_and_mysql_aligned(): void
    {
        $requiredDocs = [
            'docs/SOW_COMPLETION_MATRIX.md' => [
                'Management & Collaboration',
                '/crm/leads',
                '/payroll/runs',
                '/operations/readiness',
                'MySQL is the accepted delivery database',
            ],
            'docs/ROLE_PERMISSION_MATRIX.md' => [
                'Director',
                'Channel Partner',
                'Executive Partner (Broker)',
                'sensitive-field visibility',
            ],
            'docs/WORKFLOW_AND_SETTINGS_CATALOGUE.md' => [
                'workflow.approval_chains',
                'hr.attendance.rules',
                'payroll.tax_rules',
                'Biometric attendance',
                'Backup/DR',
            ],
            'docs/UAT_ACCEPTANCE_CHECKLIST.md' => [
                'MySQL setup',
                'Partner Portal',
                'Buyer Portal',
                'Final Acceptance',
            ],
            'docs/LOCAL_HOSTING_AND_HANDOVER.md' => [
                'php artisan migrate --seed',
                'DB_CONNECTION=mysql',
                'Legacy SQLite-only backup utility',
                'Handover Pack',
            ],
            'docs/WEBUZO_UBUNTU_DEPLOYMENT.md' => [
                "Laravel's `public` directory",
                'builder360:reconcile --json',
                'Queue, scheduler and Reverb',
                'Rollback',
            ],
        ];

        foreach ($requiredDocs as $path => $expectedFragments) {
            $contents = file_get_contents(base_path($path));

            $this->assertIsString($contents, "{$path} must exist.");

            foreach ($expectedFragments as $fragment) {
                $this->assertStringContainsString($fragment, $contents, "{$path} must document [{$fragment}].");
            }
        }

        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertIsString($envExample);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $envExample);
        $this->assertStringContainsString('DB_DATABASE=builder360', $envExample);
        $this->assertStringContainsString('DB_FOREIGN_KEYS=true', $envExample);
        $this->assertStringContainsString('BUILDER360_EXTERNAL_DB_BACKUP_VERIFIED=false', $envExample);

        $productionEnv = file_get_contents(base_path('deployment/webuzo/.env.production.example'));

        $this->assertIsString($productionEnv);
        $this->assertStringContainsString('APP_ENV=production', $productionEnv);
        $this->assertStringContainsString('APP_DEBUG=false', $productionEnv);
        $this->assertStringContainsString('SESSION_ENCRYPT=true', $productionEnv);
        $this->assertStringContainsString('REVERB_ALLOWED_ORIGINS=', $productionEnv);
    }

    public function test_blade_pages_do_not_expose_framework_copy(): void
    {
        $views = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'))
        );

        $checked = 0;

        foreach ($views as $view) {
            if (! $view instanceof SplFileInfo || ! $view->isFile() || ! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            $checked++;
            $contents = file_get_contents($view->getPathname());

            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $view->getPathname());

            $this->assertIsString($contents);
            $this->assertStringNotContainsString('Native Laravel Blade', $contents, "{$relativePath} should use business-facing copy.");
            $this->assertStringNotContainsString('Laravel Blade', $contents, "{$relativePath} should use business-facing copy.");
            $this->assertStringNotContainsString('Laravel SQLite', $contents, "{$relativePath} should use business-facing copy.");
        }

        $this->assertGreaterThan(40, $checked, 'The framework-copy regression should cover the Blade UI surface.');
    }
}
