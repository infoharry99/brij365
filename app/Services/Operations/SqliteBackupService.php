<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SqliteBackupService
{
    /**
     * @return array<string, mixed>
     */
    public function backup(?string $outputDirectory = null, ?int $retentionDays = null): array
    {
        $sourcePath = $this->sqliteDatabasePath();
        $relativeDirectory = $this->safeRelativeDirectory($outputDirectory ?: $this->configuredDirectory());
        $targetDirectory = $this->privateStoragePath($relativeDirectory);

        $this->ensureDirectory($targetDirectory);
        $this->ensurePathInsidePrivateStorage($targetDirectory);
        $this->checkpointSqliteConnection();

        $timestamp = now()->format('Ymd-His');
        $backupRelativePath = $relativeDirectory.'/builder360-sqlite-'.$timestamp.'.sqlite';
        $manifestRelativePath = $backupRelativePath.'.json';
        $backupPath = $this->privateStoragePath($backupRelativePath);
        $manifestPath = $this->privateStoragePath($manifestRelativePath);

        if (is_file($backupPath)) {
            $suffix = bin2hex(random_bytes(4));
            $backupRelativePath = $relativeDirectory.'/builder360-sqlite-'.$timestamp.'-'.$suffix.'.sqlite';
            $manifestRelativePath = $backupRelativePath.'.json';
            $backupPath = $this->privateStoragePath($backupRelativePath);
            $manifestPath = $this->privateStoragePath($manifestRelativePath);
        }

        $method = $this->copyDatabase($sourcePath, $backupPath);
        $checksum = hash_file('sha256', $backupPath);
        $size = filesize($backupPath);

        if ($checksum === false || $size === false || $size < 1) {
            @unlink($backupPath);

            throw new RuntimeException('SQLite backup copy could not be verified.');
        }

        $manifest = [
            'schema' => 'builder360.sqlite-backup.v1',
            'created_at' => now()->toISOString(),
            'application' => config('app.name'),
            'environment' => config('app.env'),
            'connection' => config('database.default'),
            'driver' => 'sqlite',
            'database_file' => basename($sourcePath),
            'backup_file' => $backupRelativePath,
            'manifest_file' => $manifestRelativePath,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'method' => $method,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        $encodedManifest = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($encodedManifest === false || file_put_contents($manifestPath, $encodedManifest.PHP_EOL, LOCK_EX) === false) {
            @unlink($backupPath);

            throw new RuntimeException('SQLite backup manifest could not be written.');
        }

        $deleted = $this->pruneExpiredBackups($targetDirectory, $this->retentionDays($retentionDays));

        return [
            'status' => 'ok',
            'backup_file' => $backupRelativePath,
            'manifest_file' => $manifestRelativePath,
            'size_bytes' => $size,
            'checksum_sha256' => $checksum,
            'method' => $method,
            'retention_days' => $this->retentionDays($retentionDays),
            'retention_deleted' => $deleted,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestManifest(?string $outputDirectory = null): ?array
    {
        $path = $this->latestManifestPath($outputDirectory);

        if ($path === null) {
            return null;
        }

        return $this->readManifest($path);
    }

    public function latestManifestRelativePath(?string $outputDirectory = null): ?string
    {
        $path = $this->latestManifestPath($outputDirectory);

        if ($path === null) {
            return null;
        }

        $privateRoot = rtrim(str_replace('\\', '/', $this->privateStoragePath()), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, $privateRoot)
            ? substr($normalized, strlen($privateRoot))
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(?string $manifestRelativePath = null): array
    {
        $manifestRelativePath = $manifestRelativePath ?: $this->latestManifestRelativePath();

        if ($manifestRelativePath === null) {
            return [
                'status' => 'degraded',
                'failure' => 'sqlite_backup_manifest_missing',
            ];
        }

        $manifestRelativePath = $this->safeRelativeFile($manifestRelativePath, '.json');
        $manifestPath = $this->privateStoragePath($manifestRelativePath);
        $manifest = $this->readManifest($manifestPath);

        if ($manifest === null || ($manifest['schema'] ?? null) !== 'builder360.sqlite-backup.v1') {
            return [
                'status' => 'degraded',
                'manifest_file' => $manifestRelativePath,
                'failure' => 'sqlite_backup_manifest_invalid',
            ];
        }

        $backupRelativePath = isset($manifest['backup_file'])
            ? $this->safeRelativeFile((string) $manifest['backup_file'], '.sqlite')
            : null;

        if ($backupRelativePath === null) {
            return [
                'status' => 'degraded',
                'manifest_file' => $manifestRelativePath,
                'failure' => 'sqlite_backup_file_missing_from_manifest',
            ];
        }

        $backupPath = $this->privateStoragePath($backupRelativePath);
        $fileExists = is_file($backupPath);
        $size = $fileExists ? filesize($backupPath) : false;
        $expectedSize = isset($manifest['size_bytes']) ? (int) $manifest['size_bytes'] : null;
        $sizeMatches = $fileExists && $size !== false && $expectedSize !== null && (int) $size === $expectedSize;
        $expectedChecksum = isset($manifest['checksum_sha256']) ? (string) $manifest['checksum_sha256'] : null;
        $actualChecksum = $fileExists ? hash_file('sha256', $backupPath) : false;
        $checksumMatches = $fileExists
            && is_string($actualChecksum)
            && is_string($expectedChecksum)
            && hash_equals($expectedChecksum, $actualChecksum);
        $integrity = $fileExists && $checksumMatches ? $this->sqliteIntegrityCheck($backupPath) : [
            'ok' => false,
            'result' => null,
            'error' => $fileExists ? 'checksum_not_verified' : 'backup_file_missing',
        ];
        $ok = $fileExists && $sizeMatches && $checksumMatches && $integrity['ok'];

        return [
            'status' => $ok ? 'ok' : 'degraded',
            'manifest_file' => $manifestRelativePath,
            'backup_file' => $backupRelativePath,
            'created_at' => $manifest['created_at'] ?? null,
            'file_exists' => $fileExists,
            'size_bytes' => $size === false ? null : (int) $size,
            'size_matches_manifest' => $sizeMatches,
            'checksum_sha256' => is_string($actualChecksum) ? $actualChecksum : null,
            'checksum_matches_manifest' => $checksumMatches,
            'integrity_check' => $integrity,
            'failure' => $ok ? null : $this->verificationFailureReason($fileExists, $sizeMatches, $checksumMatches, $integrity['ok']),
        ];
    }

    public function configuredDirectory(): string
    {
        return (string) config('builder360.backups.sqlite.directory', 'backups/sqlite');
    }

    public function configuredRetentionDays(): int
    {
        return $this->retentionDays(null);
    }

    public function configuredMaxAgeHours(): int
    {
        return max(1, (int) config('builder360.backups.sqlite.max_age_hours', 24));
    }

    public function privateStoragePath(string $relativePath = ''): string
    {
        $root = storage_path('app/private');

        return $relativePath === ''
            ? $root
            : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function sqliteDatabasePath(): string
    {
        $connection = (string) config('database.default');

        if (DB::connection($connection)->getDriverName() !== 'sqlite') {
            throw new RuntimeException('The configured database connection is not SQLite.');
        }

        $database = config("database.connections.{$connection}.database");

        if ($database === ':memory:') {
            throw new RuntimeException('SQLite in-memory databases cannot be backed up to disk.');
        }

        if (! is_string($database) || trim($database) === '') {
            throw new RuntimeException('SQLite database path is not configured.');
        }

        $path = $this->absolutePath($database);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('SQLite database file is missing or not readable.');
        }

        return $path;
    }

    private function absolutePath(string $path): string
    {
        return match (true) {
            str_starts_with($path, '/'),
            str_starts_with($path, '\\'),
            preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 => $path,
            default => base_path($path),
        };
    }

    private function safeRelativeDirectory(string $directory): string
    {
        $directory = trim(str_replace('\\', '/', $directory), '/');

        if ($directory === '') {
            throw new RuntimeException('Backup directory cannot be blank.');
        }

        if (
            str_starts_with($directory, '/')
            || preg_match('/^[A-Za-z]:/', $directory) === 1
            || str_contains($directory, '..')
            || str_contains($directory, ':')
            || preg_match('/(^|\/)\.(\/|$)/', $directory) === 1
            || preg_match('/[\x00-\x1F]/', $directory) === 1
        ) {
            throw new RuntimeException('Backup directory must be a safe relative path inside private storage.');
        }

        $segments = array_filter(explode('/', $directory), fn (string $segment): bool => $segment !== '');

        if ($segments === []) {
            throw new RuntimeException('Backup directory cannot be blank.');
        }

        foreach ($segments as $segment) {
            if (! preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new RuntimeException('Backup directory contains unsupported characters.');
            }
        }

        return implode('/', $segments);
    }

    private function safeRelativeFile(string $path, string $requiredSuffix): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        if (
            $path === ''
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || str_contains($path, '..')
            || str_contains($path, ':')
            || preg_match('/(^|\/)\.(\/|$)/', $path) === 1
            || preg_match('/[\x00-\x1F]/', $path) === 1
            || ! str_ends_with($path, $requiredSuffix)
        ) {
            throw new RuntimeException('Backup file path must be a safe relative path inside private storage.');
        }

        $segments = array_filter(explode('/', $path), fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if (! preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
                throw new RuntimeException('Backup file path contains unsupported characters.');
            }
        }

        return implode('/', $segments);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException('Backup directory could not be created.');
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('Backup directory is not writable.');
        }
    }

    private function ensurePathInsidePrivateStorage(string $path): void
    {
        $root = realpath($this->privateStoragePath());
        $target = realpath($path);

        if ($root === false || $target === false) {
            throw new RuntimeException('Backup storage path could not be resolved.');
        }

        $root = rtrim(strtolower($root), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $target = rtrim(strtolower($target), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if (! str_starts_with($target, $root)) {
            throw new RuntimeException('Backup path escaped private storage.');
        }
    }

    private function checkpointSqliteConnection(): void
    {
        try {
            DB::statement('pragma wal_checkpoint(TRUNCATE)');
        } catch (Throwable) {
            // Some SQLite modes do not use WAL. Backup can still continue.
        }
    }

    private function copyDatabase(string $sourcePath, string $backupPath): string
    {
        if (class_exists('SQLite3') && method_exists('SQLite3', 'backup')) {
            $source = new \SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new \SQLite3($backupPath);

            try {
                if ($source->backup($destination) !== true) {
                    throw new RuntimeException('SQLite online backup failed.');
                }
            } finally {
                $destination->close();
                $source->close();
            }

            return 'sqlite3_online_backup';
        }

        if (! copy($sourcePath, $backupPath)) {
            throw new RuntimeException('SQLite file copy failed.');
        }

        return 'file_copy';
    }

    private function retentionDays(?int $retentionDays): int
    {
        $days = $retentionDays ?? (int) config('builder360.backups.sqlite.retention_days', 30);

        return max(1, $days);
    }

    private function pruneExpiredBackups(string $targetDirectory, int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $deleted = 0;
        $files = glob($targetDirectory.DIRECTORY_SEPARATOR.'builder360-sqlite-*.sqlite*') ?: [];
        $targetRoot = realpath($targetDirectory);

        if ($targetRoot === false) {
            return 0;
        }

        $targetRoot = rtrim(strtolower(str_replace('\\', '/', $targetRoot)), '/').'/';

        foreach ($files as $file) {
            clearstatcache(false, $file);

            $resolved = realpath($file);

            if ($resolved === false) {
                continue;
            }

            $resolved = strtolower(str_replace('\\', '/', $resolved));

            if (! str_starts_with($resolved, $targetRoot)) {
                continue;
            }

            if (filemtime($file) !== false && filemtime($file) < $cutoff && is_file($file)) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private function latestManifestPath(?string $outputDirectory = null): ?string
    {
        $relativeDirectory = $this->safeRelativeDirectory($outputDirectory ?: $this->configuredDirectory());
        $targetDirectory = $this->privateStoragePath($relativeDirectory);

        if (! is_dir($targetDirectory)) {
            return null;
        }

        $manifests = glob($targetDirectory.DIRECTORY_SEPARATOR.'builder360-sqlite-*.sqlite.json') ?: [];

        if ($manifests === []) {
            return null;
        }

        usort($manifests, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $manifests[0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $path): ?array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{ok: bool, result: string|null, error: string|null}
     */
    private function sqliteIntegrityCheck(string $backupPath): array
    {
        try {
            if (class_exists('SQLite3')) {
                $database = new \SQLite3($backupPath, SQLITE3_OPEN_READONLY);

                try {
                    $result = $database->querySingle('pragma integrity_check');
                } finally {
                    $database->close();
                }

                return [
                    'ok' => $result === 'ok',
                    'result' => is_string($result) ? $result : null,
                    'error' => null,
                ];
            }

            $pdo = new \PDO('sqlite:'.$backupPath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $result = $pdo->query('pragma integrity_check')?->fetchColumn();

            return [
                'ok' => $result === 'ok',
                'result' => is_string($result) ? $result : null,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'result' => null,
                'error' => class_basename($exception),
            ];
        }
    }

    private function verificationFailureReason(bool $fileExists, bool $sizeMatches, bool $checksumMatches, bool $integrityOk): string
    {
        if (! $fileExists) {
            return 'sqlite_backup_file_missing';
        }

        if (! $sizeMatches) {
            return 'sqlite_backup_size_mismatch';
        }

        if (! $checksumMatches) {
            return 'sqlite_backup_checksum_mismatch';
        }

        if (! $integrityOk) {
            return 'sqlite_backup_integrity_check_failed';
        }

        return 'sqlite_backup_verification_failed';
    }
}
