<?php

namespace App\Services\Documents;

class DocumentFilePolicy
{
    /**
     * @return array<string, array<int, string>>
     */
    public function violations(
        string $mimeType,
        int $fileSizeBytes,
        string $checksumSha256,
        string $originalFilename,
        string $storagePath,
    ): array {
        $violations = [];
        $mimeType = strtolower(trim($mimeType));
        $checksumSha256 = strtolower(trim($checksumSha256));
        $originalExtension = $this->extensionFrom($originalFilename);
        $storageExtension = $this->extensionFrom($storagePath);

        if (! in_array($mimeType, $this->allowedMimeTypes(), true)) {
            $violations['mime_type'][] = 'The document MIME type is not allowed by the configured document file policy.';
        }

        if ($fileSizeBytes < 1 || $fileSizeBytes > $this->maxFileSizeBytes()) {
            $violations['file_size_bytes'][] = 'The document file size exceeds the configured document upload limit.';
        }

        if (! preg_match('/\A[a-f0-9]{64}\z/', $checksumSha256)) {
            $violations['checksum_sha256'][] = 'The document checksum must be a valid SHA-256 hex digest.';
        }

        if ($originalExtension === '' || ! in_array($originalExtension, $this->allowedExtensions(), true)) {
            $violations['original_filename'][] = 'The original filename extension is not allowed by the configured document file policy.';
        }

        if ($storageExtension === '' || ! in_array($storageExtension, $this->allowedExtensions(), true)) {
            $violations['storage_path'][] = 'The storage path extension is not allowed by the configured document file policy.';
        }

        if ($originalExtension !== '' && $storageExtension !== '' && $originalExtension !== $storageExtension) {
            $violations['storage_path'][] = 'The storage path extension must match the original filename extension.';
        }

        return $violations;
    }

    /**
     * @return array<string, mixed>
     */
    public function readiness(): array
    {
        $allowedExtensions = $this->allowedExtensions();
        $allowedMimeTypes = $this->allowedMimeTypes();
        $dangerousExtensions = array_values(array_intersect($allowedExtensions, $this->dangerousExtensions()));
        $unsafeMimeTypes = array_values(array_filter(
            $allowedMimeTypes,
            fn (string $mimeType): bool => in_array($mimeType, ['*', '*/*', 'application/octet-stream'], true)
                || str_contains($mimeType, '*')
        ));
        $maxFileSizeKb = $this->maxFileSizeKb();
        $maxFileSizeCeilingKb = $this->maxFileSizeCeilingKb();
        $checksumAlgorithms = $this->allowedChecksumAlgorithms();
        $unsupportedChecksumAlgorithms = array_values(array_diff($checksumAlgorithms, ['sha256']));
        $requirements = [
            'allowed_extensions_configured' => $allowedExtensions !== [],
            'dangerous_extensions_blocked' => $dangerousExtensions === [],
            'allowed_mime_types_configured' => $allowedMimeTypes !== [],
            'wildcard_mime_types_blocked' => $unsafeMimeTypes === [],
            'max_file_size_positive' => $maxFileSizeKb > 0,
            'max_file_size_within_ceiling' => $maxFileSizeKb <= $maxFileSizeCeilingKb,
            'sha256_checksum_supported' => in_array('sha256', $checksumAlgorithms, true),
            'unsupported_checksum_algorithms_blocked' => $unsupportedChecksumAlgorithms === [],
        ];
        $ready = ! in_array(false, $requirements, true);

        return [
            'status' => $ready ? 'ok' : 'degraded',
            'allowed_extensions' => $allowedExtensions,
            'dangerous_extensions' => $dangerousExtensions,
            'allowed_mime_types' => $allowedMimeTypes,
            'unsafe_mime_types' => $unsafeMimeTypes,
            'max_file_size_kb' => $maxFileSizeKb,
            'max_file_size_ceiling_kb' => $maxFileSizeCeilingKb,
            'checksum_required' => true,
            'allowed_checksum_algorithms' => $checksumAlgorithms,
            'unsupported_checksum_algorithms' => $unsupportedChecksumAlgorithms,
            'requirements' => $requirements,
            'failure' => $ready ? null : $this->failureReason($requirements),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return $this->configuredStringList('builder360.documents.allowed_extensions', fn (string $extension): string => strtolower(ltrim($extension, '.')));
    }

    /**
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return $this->configuredStringList('builder360.documents.allowed_mime_types', fn (string $mimeType): string => strtolower($mimeType));
    }

    public function maxFileSizeKb(): int
    {
        return (int) config('builder360.documents.max_file_size_kb', 10240);
    }

    public function maxFileSizeBytes(): int
    {
        return $this->maxFileSizeKb() * 1024;
    }

    public function maxFileSizeCeilingKb(): int
    {
        return (int) config('builder360.documents.max_file_size_ceiling_kb', 51200);
    }

    /**
     * @return array<int, string>
     */
    public function allowedChecksumAlgorithms(): array
    {
        return $this->configuredStringList('builder360.documents.allowed_checksum_algorithms', fn (string $algorithm): string => strtolower($algorithm));
    }

    private function extensionFrom(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        return strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * @return array<int, string>
     */
    private function dangerousExtensions(): array
    {
        return [
            'bat',
            'cmd',
            'com',
            'exe',
            'htm',
            'html',
            'jar',
            'js',
            'phtml',
            'phar',
            'php',
            'ps1',
            'sh',
            'svg',
        ];
    }

    /**
     * @param  callable(string): string  $normalizer
     * @return array<int, string>
     */
    private function configuredStringList(string $key, callable $normalizer): array
    {
        $values = array_map(
            fn (mixed $value): string => $normalizer(trim((string) $value)),
            (array) config($key, []),
        );

        return array_values(array_unique(array_filter(
            $values,
            fn (string $value): bool => $value !== '',
        )));
    }

    /**
     * @param  array<string, bool>  $requirements
     */
    private function failureReason(array $requirements): ?string
    {
        foreach ($requirements as $requirement => $passed) {
            if (! $passed) {
                return 'document_upload_'.$requirement;
            }
        }

        return null;
    }
}
