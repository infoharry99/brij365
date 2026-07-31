<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SqliteBackupCommandTest extends TestCase
{
    private string $databasePath;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/builder360-sqlite-backup-test.sqlite');
        $this->backupDirectory = storage_path('app/private/testing/sqlite-backups');

        $this->deletePathInsideStorage($this->databasePath);
        $this->deleteDirectoryInsideStorage($this->backupDirectory);

        File::ensureDirectoryExists(dirname($this->databasePath), 0755, true);
        File::ensureDirectoryExists(dirname($this->backupDirectory), 0755, true);
        File::put($this->databasePath, '');

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', $this->databasePath);
        Config::set('database.connections.sqlite.foreign_key_constraints', true);
        Config::set('builder360.backups.sqlite.directory', 'testing/sqlite-backups');
        Config::set('builder360.backups.sqlite.retention_days', 30);
        Config::set('builder360.backups.sqlite.max_age_hours', 24);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::statement('create table backup_probe (id integer primary key, name varchar not null)');
        DB::insert('insert into backup_probe (name) values (?)', ['Builder360 backup test']);
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');

        $this->deletePathInsideStorage($this->databasePath);
        $this->deleteDirectoryInsideStorage($this->backupDirectory);

        parent::tearDown();
    }

    public function test_sqlite_backup_command_creates_private_backup_manifest_and_json_output(): void
    {
        $exitCode = Artisan::call('builder360:sqlite-backup', [
            '--output-dir' => 'testing/sqlite-backups',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertStringStartsWith('testing/sqlite-backups/builder360-sqlite-', $payload['backup_file']);
        $this->assertSame($payload['backup_file'].'.json', $payload['manifest_file']);
        $this->assertGreaterThan(0, $payload['size_bytes']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $payload['checksum_sha256']);

        $backupPath = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $payload['backup_file']));
        $manifestPath = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $payload['manifest_file']));

        $this->assertFileExists($backupPath);
        $this->assertFileExists($manifestPath);
        $this->assertSame($payload['checksum_sha256'], hash_file('sha256', $backupPath));

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('builder360.sqlite-backup.v1', $manifest['schema']);
        $this->assertSame(basename($this->databasePath), $manifest['database_file']);
        $this->assertArrayNotHasKey('database_path', $manifest);

        $pdo = new \PDO('sqlite:'.$backupPath);
        $count = (int) $pdo->query('select count(*) from backup_probe')->fetchColumn();

        $this->assertSame(1, $count);
    }

    public function test_sqlite_backup_verify_command_validates_latest_and_explicit_manifest(): void
    {
        Artisan::call('builder360:sqlite-backup', [
            '--output-dir' => 'testing/sqlite-backups',
            '--json' => true,
        ]);

        $backupPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $latestExitCode = Artisan::call('builder360:sqlite-backup-verify', [
            '--json' => true,
        ]);
        $latestPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $latestExitCode);
        $this->assertSame('ok', $latestPayload['status']);
        $this->assertSame($backupPayload['manifest_file'], $latestPayload['manifest_file']);
        $this->assertTrue($latestPayload['checksum_matches_manifest']);
        $this->assertTrue($latestPayload['integrity_check']['ok']);
        $this->assertSame('ok', $latestPayload['integrity_check']['result']);

        $explicitExitCode = Artisan::call('builder360:sqlite-backup-verify', [
            'manifest' => $backupPayload['manifest_file'],
            '--json' => true,
        ]);
        $explicitPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $explicitExitCode);
        $this->assertSame('ok', $explicitPayload['status']);
        $this->assertSame($backupPayload['backup_file'], $explicitPayload['backup_file']);
    }

    public function test_sqlite_backup_verify_command_detects_corrupted_backup_file(): void
    {
        Artisan::call('builder360:sqlite-backup', [
            '--output-dir' => 'testing/sqlite-backups',
            '--json' => true,
        ]);

        $backupPayload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $backupPath = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $backupPayload['backup_file']));

        File::append($backupPath, 'corruption');

        $exitCode = Artisan::call('builder360:sqlite-backup-verify', [
            'manifest' => $backupPayload['manifest_file'],
            '--json' => true,
        ]);
        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('degraded', $payload['status']);
        $this->assertFalse($payload['size_matches_manifest']);
        $this->assertFalse($payload['checksum_matches_manifest']);
        $this->assertSame('sqlite_backup_size_mismatch', $payload['failure']);
    }

    public function test_sqlite_backup_verify_command_rejects_unsafe_manifest_path(): void
    {
        $exitCode = Artisan::call('builder360:sqlite-backup-verify', [
            'manifest' => '../escaped.json',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertSame('RuntimeException', $payload['error']);
        $this->assertStringContainsString('safe relative path', $payload['message']);
    }

    public function test_sqlite_backup_command_rejects_unsafe_output_directory(): void
    {
        $exitCode = Artisan::call('builder360:sqlite-backup', [
            '--output-dir' => '../escaped',
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('failed', $payload['status']);
        $this->assertSame('RuntimeException', $payload['error']);
        $this->assertStringContainsString('safe relative path', $payload['message']);
    }

    public function test_sqlite_backup_command_prunes_expired_backup_files_inside_private_directory(): void
    {
        File::ensureDirectoryExists($this->backupDirectory, 0750, true);

        $oldBackup = $this->backupDirectory.DIRECTORY_SEPARATOR.'builder360-sqlite-20000101-000000.sqlite';
        $oldManifest = $oldBackup.'.json';

        File::put($oldBackup, 'old-backup');
        File::put($oldManifest, '{}');
        touch($oldBackup, now()->subDays(10)->getTimestamp());
        touch($oldManifest, now()->subDays(10)->getTimestamp());

        $exitCode = Artisan::call('builder360:sqlite-backup', [
            '--output-dir' => 'testing/sqlite-backups',
            '--retention-days' => 1,
            '--json' => true,
        ]);

        $payload = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $payload['status']);
        $this->assertGreaterThanOrEqual(2, $payload['retention_deleted']);
        $this->assertFileDoesNotExist($oldBackup);
        $this->assertFileDoesNotExist($oldManifest);
    }

    private function deletePathInsideStorage(string $path): void
    {
        if (is_file($path) && str_starts_with(strtolower($path), strtolower(storage_path('framework/testing')))) {
            File::delete($path);
        }
    }

    private function deleteDirectoryInsideStorage(string $path): void
    {
        $allowedRoot = strtolower(storage_path('app/private/testing'));

        if (is_dir($path) && str_starts_with(strtolower($path), $allowedRoot)) {
            File::deleteDirectory($path);
        }
    }
}
