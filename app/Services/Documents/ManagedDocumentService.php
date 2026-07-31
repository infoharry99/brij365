<?php

namespace App\Services\Documents;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\ManagedDocument;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManagedDocumentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DocumentStoragePolicy $documentStoragePolicy,
        private readonly DocumentFilePolicy $documentFilePolicy,
        private readonly CompanyScopeService $companyScope,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function submit(array $data, User $actor, ?Request $request = null): ManagedDocument
    {
        $storedUploadDisk = null;
        $storedUploadPath = null;

        try {
            return DB::transaction(function () use ($data, $actor, $request, &$storedUploadDisk, &$storedUploadPath): ManagedDocument {
                $data = $this->normalizeUploadedFile($data);

                if (($data['_stored_upload'] ?? false) === true) {
                    $storedUploadDisk = (string) $data['storage_disk'];
                    $storedUploadPath = (string) $data['storage_path'];
                    unset($data['_stored_upload']);
                }

            $this->documentStoragePolicy->assertValid($data);
            $fileViolations = $this->documentFilePolicy->violations(
                (string) $data['mime_type'],
                (int) $data['file_size_bytes'],
                (string) $data['checksum_sha256'],
                (string) $data['original_filename'],
                (string) $data['storage_path'],
            );

            if ($fileViolations !== []) {
                throw ValidationException::withMessages($fileViolations);
            }

            $category = DocumentCategory::query()->whereKey($data['document_category_id'])->firstOrFail();
            $owner = $this->resolveOwner($data['owner_type'], (int) $data['owner_id']);

            if (! $owner) {
                throw ValidationException::withMessages(['owner_id' => 'The selected document owner does not exist.']);
            }

            $companyId = $this->ownerCompanyId($owner, $actor);

            if ($companyId <= 0 || ! $this->companyScope->allows($actor, $companyId)) {
                throw ValidationException::withMessages([
                    'owner_id' => 'The selected document owner is outside your company scope.',
                ]);
            }

            if ($category->company_id !== null && ! $this->companyScope->allows($actor, $category->company_id)) {
                throw ValidationException::withMessages([
                    'document_category_id' => 'The selected document category is outside your company scope.',
                ]);
            }

            if ($category->company_id !== null && (int) $category->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    'document_category_id' => 'The selected category is outside the owner company scope.',
                ]);
            }

            $version = $this->nextVersion($companyId, $category->id, $data['owner_type'], (int) $data['owner_id'], $data['title']);

            ManagedDocument::query()
                ->where('company_id', $companyId)
                ->where('document_category_id', $category->id)
                ->where('owner_type', $data['owner_type'])
                ->where('owner_id', $data['owner_id'])
                ->where('title', $data['title'])
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $document = ManagedDocument::create([
                'company_id' => $companyId,
                'document_category_id' => $category->id,
                'uploaded_by_user_id' => $actor->id,
                'document_number' => $this->nextDocumentNumber(),
                'title' => $data['title'],
                'owner_type' => $data['owner_type'],
                'owner_id' => $data['owner_id'],
                'status' => 'submitted',
                'storage_disk' => $data['storage_disk'] ?? 'local',
                'storage_path' => $data['storage_path'],
                'original_filename' => $data['original_filename'],
                'mime_type' => $data['mime_type'],
                'file_size_bytes' => $data['file_size_bytes'],
                'checksum_sha256' => $data['checksum_sha256'],
                'issue_date' => $data['issue_date'] ?? null,
                'expires_on' => $data['expires_on'] ?? null,
                'version' => $version,
                'is_current' => true,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $this->auditLogger->record(
                $actor,
                'documents.document.submitted',
                'Submitted managed document',
                $document,
                [
                    'document_number' => $document->document_number,
                    'owner_type' => $document->owner_type,
                    'owner_id' => $document->owner_id,
                    'category_code' => $category->code,
                ],
                $request,
            );

            return $document->load($this->relations());
            });
        } catch (Throwable $exception) {
            if ($storedUploadDisk && $storedUploadPath) {
                Storage::disk($storedUploadDisk)->delete($storedUploadPath);
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(ManagedDocument $document, array $data, User $actor, ?Request $request = null): ManagedDocument
    {
        return DB::transaction(function () use ($document, $data, $actor, $request): ManagedDocument {
            $lockedDocument = ManagedDocument::query()
                ->with('category')
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDocument->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'document' => 'Only submitted documents can be approved.',
                ]);
            }

            if (! $this->companyScope->allows($actor, $lockedDocument->company_id)) {
                throw ValidationException::withMessages([
                    'document' => 'The selected document is outside your company scope.',
                ]);
            }

            if ($lockedDocument->uploaded_by_user_id === $actor->id) {
                throw ValidationException::withMessages([
                    'document' => 'The uploader cannot approve the same document.',
                ]);
            }

            if ($lockedDocument->category->expiry_required && $lockedDocument->expires_on?->isPast()) {
                throw ValidationException::withMessages([
                    'expires_on' => 'Expired documents cannot be approved.',
                ]);
            }

            $lockedDocument->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'metadata' => array_merge($lockedDocument->metadata ?? [], [
                    'approval_note' => $data['approval_note'] ?? null,
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'documents.document.approved',
                'Approved managed document',
                $lockedDocument,
                [
                    'document_number' => $lockedDocument->document_number,
                    'owner_type' => $lockedDocument->owner_type,
                    'owner_id' => $lockedDocument->owner_id,
                    'approval_note' => $data['approval_note'] ?? null,
                ],
                $request,
            );

            return $lockedDocument->load($this->relations());
        });
    }

    private function resolveOwner(string $ownerType, int $ownerId): ?Model
    {
        return match ($ownerType) {
            'project' => Project::find($ownerId),
            'booking' => Booking::find($ownerId),
            'customer' => Customer::find($ownerId),
            'employee' => Employee::find($ownerId),
            default => null,
        };
    }

    private function ownerCompanyId(Model $owner, User $actor): int
    {
        if ($owner instanceof Customer) {
            $companyIds = $this->customerCompanyIds($owner, $actor);

            if ($companyIds->count() !== 1) {
                return 0;
            }

            return (int) $companyIds->first();
        }

        return (int) $owner->getAttribute('company_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function customerCompanyIds(Customer $customer, User $actor): \Illuminate\Support\Collection
    {
        $companyIds = collect()
            ->merge(Booking::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(Lead::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(ServiceTicket::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(ManagedDocument::query()->where('owner_type', 'customer')->where('owner_id', $customer->id)->pluck('company_id'))
            ->filter()
            ->map(fn ($companyId): int => (int) $companyId)
            ->unique()
            ->values();

        if ($this->companyScope->hasUnrestrictedCompanyScope($actor)) {
            return $companyIds;
        }

        return $companyIds
            ->filter(fn (int $companyId): bool => $this->companyScope->allows($actor, $companyId))
            ->values();
    }

    private function nextVersion(int $companyId, int $categoryId, string $ownerType, int $ownerId, string $title): int
    {
        $latestVersion = ManagedDocument::query()
            ->where('company_id', $companyId)
            ->where('document_category_id', $categoryId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('title', $title)
            ->max('version');

        return ((int) $latestVersion) + 1;
    }

    private function nextDocumentNumber(): string
    {
        return sprintf('DOC-%04d', ManagedDocument::query()->withTrashed()->count() + 1001);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeUploadedFile(array $data): array
    {
        $uploadedFile = $data['document_file'] ?? null;

        if (! $uploadedFile instanceof UploadedFile) {
            return $data;
        }

        if (! $uploadedFile->isValid()) {
            throw ValidationException::withMessages([
                'document_file' => 'The uploaded document file is not valid.',
            ]);
        }

        $originalFilename = $uploadedFile->getClientOriginalName();
        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $storageDisk = (string) ($data['storage_disk'] ?? 'local');
        $storagePath = $this->documentStoragePolicy->storagePathPrefix()
            .'uploads/'
            .now('UTC')->format('Y/m')
            .'/'
            .Str::uuid()->toString()
            .($extension !== '' ? '.'.$extension : '');
        $mimeType = $uploadedFile->getMimeType() ?: $uploadedFile->getClientMimeType() ?: 'application/octet-stream';
        $fileSize = (int) $uploadedFile->getSize();
        $realPath = $uploadedFile->getRealPath();

        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            throw ValidationException::withMessages([
                'document_file' => 'The uploaded document file could not be read.',
            ]);
        }

        $checksumSha256 = hash_file('sha256', $realPath);

        $this->documentStoragePolicy->assertValid([
            'storage_disk' => $storageDisk,
            'storage_path' => $storagePath,
            'original_filename' => $originalFilename,
        ]);

        $fileViolations = $this->documentFilePolicy->violations(
            $mimeType,
            $fileSize,
            $checksumSha256,
            $originalFilename,
            $storagePath,
        );

        if ($fileViolations !== []) {
            throw ValidationException::withMessages($fileViolations);
        }

        $stream = fopen($realPath, 'rb');

        if ($stream === false || Storage::disk($storageDisk)->put($storagePath, $stream) === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw ValidationException::withMessages([
                'document_file' => 'The uploaded document file could not be stored.',
            ]);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        $metadata = array_merge((array) ($data['metadata'] ?? []), [
            'source' => $data['metadata']['source'] ?? 'document_management_upload',
            'upload_mode' => 'multipart_file',
            'checksum_algorithm' => 'sha256',
        ]);

        unset($data['document_file']);

        return array_merge($data, [
            'storage_disk' => $storageDisk,
            'storage_path' => $storagePath,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size_bytes' => $fileSize,
            'checksum_sha256' => $checksumSha256,
            'metadata' => $metadata,
            '_stored_upload' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['company', 'category', 'uploadedBy', 'approvedBy', 'employeeOwner'];
    }
}
