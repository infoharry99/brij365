<?php

namespace App\Services\Documents;

use Illuminate\Validation\ValidationException;

class DocumentStoragePolicy
{
    /**
     * @param array<string, mixed> $data
     */
    public function assertValid(array $data): void
    {
        $violations = $this->violations(
            (string) ($data['storage_disk'] ?? 'local'),
            (string) ($data['storage_path'] ?? ''),
            (string) ($data['original_filename'] ?? ''),
        );

        if ($violations !== []) {
            throw ValidationException::withMessages($violations);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function violations(string $disk, string $path, string $originalFilename): array
    {
        $violations = [];
        $disk = trim($disk);
        $path = trim($path);
        $originalFilename = trim($originalFilename);

        if (! in_array($disk, $this->allowedDisks(), true)) {
            $violations['storage_disk'][] = 'The selected storage disk is not allowed for managed documents.';
        }

        if ($this->hasUnsafePath($path)) {
            $violations['storage_path'][] = 'The storage path must be a relative managed-document path without traversal, URLs, drive letters or unsafe separators.';
        }

        $prefix = $this->storagePathPrefix();

        if ($path !== '' && ! str_starts_with($path, $prefix)) {
            $violations['storage_path'][] = 'The storage path must be inside the configured managed-document prefix: '.$prefix;
        }

        if ($this->hasUnsafeFilename($originalFilename)) {
            $violations['original_filename'][] = 'The original filename must not contain paths, control characters or traversal segments.';
        }

        return $violations;
    }

    /**
     * @return array<int, string>
     */
    public function allowedDisks(): array
    {
        return array_values(array_filter(
            (array) config('builder360.documents.allowed_storage_disks', ['local', 's3']),
            fn (mixed $disk): bool => is_string($disk) && trim($disk) !== '',
        ));
    }

    public function storagePathPrefix(): string
    {
        $prefix = trim((string) config('builder360.documents.storage_path_prefix', 'documents/'));
        $prefix = str_replace('\\', '/', $prefix);
        $prefix = ltrim($prefix, '/');

        return str_ends_with($prefix, '/') ? $prefix : $prefix.'/';
    }

    private function hasUnsafePath(string $path): bool
    {
        if ($path === '') {
            return true;
        }

        if (str_contains($path, '\\')) {
            return true;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return true;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $path) === 1) {
            return true;
        }

        if (preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1) {
            return true;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return true;
        }

        $segments = explode('/', $path);

        return collect($segments)->contains(fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..');
    }

    private function hasUnsafeFilename(string $filename): bool
    {
        if ($filename === '') {
            return true;
        }

        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return true;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $filename) === 1) {
            return true;
        }

        return in_array($filename, ['.', '..'], true) || str_contains($filename, '..');
    }
}
