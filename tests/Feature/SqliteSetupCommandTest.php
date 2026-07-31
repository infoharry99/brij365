<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SqliteSetupCommandTest extends TestCase
{
    public function test_sqlite_prepare_creates_missing_relative_database_file(): void
    {
        $relativePath = 'database/testing-sqlite-prepare.sqlite';
        $absolutePath = base_path($relativePath);

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $relativePath);

        $exitCode = Artisan::call('builder360:sqlite-prepare');

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($absolutePath);

        unlink($absolutePath);
    }

    public function test_sqlite_prepare_is_noop_for_memory_database(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        $exitCode = Artisan::call('builder360:sqlite-prepare');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No file preparation required', Artisan::output());
    }

    public function test_sqlite_prepare_is_noop_for_non_sqlite_connections(): void
    {
        Config::set('database.default', 'mysql');

        $exitCode = Artisan::call('builder360:sqlite-prepare');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('not sqlite', Artisan::output());
    }
}
