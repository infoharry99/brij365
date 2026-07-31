<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PeopleAccessibilityContractTest extends TestCase
{
    public function test_people_tables_have_accessible_captions_and_scoped_headers(): void
    {
        foreach ($this->peopleBladeFiles() as $path) {
            $source = file_get_contents($path);
            preg_match_all('/<table\b[^>]*>.*?<\/table>/is', $source, $tables);

            foreach ($tables[0] as $index => $table) {
                $label = str_replace(base_path().'/', '', str_replace('\\', '/', $path)).' table '.($index + 1);

                $this->assertMatchesRegularExpression('/<caption\b[^>]*>.*?<\/caption>/is', $table, "{$label} must describe its data with a caption.");
                $this->assertDoesNotMatchRegularExpression('/<th\b(?![^>]*\bscope=)[^>]*>/i', $table, "{$label} contains a header without an explicit scope.");
            }
        }
    }

    public function test_people_validation_alerts_can_receive_recovery_focus(): void
    {
        foreach ($this->peopleBladeFiles() as $path) {
            $source = file_get_contents($path);
            preg_match_all('/<[^>]+role=["\']alert["\'][^>]*>/i', $source, $alerts);

            foreach ($alerts[0] as $alert) {
                $this->assertStringContainsString('tabindex="-1"', $alert, str_replace(base_path().'/', '', str_replace('\\', '/', $path)).' contains a non-focusable error alert.');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function peopleBladeFiles(): array
    {
        $files = [];

        foreach (['resources/views/hr', 'resources/views/payroll', 'resources/views/recruitment'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
