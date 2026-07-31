<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagedDocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isTaxProof = $this->resource->taxDeclarations()->exists();
        $canViewStorageMetadata = $this->canViewStorageMetadata($request, $isTaxProof);

        return [
            'id' => $this->id,
            'document_number' => $this->document_number,
            'title' => $this->title,
            'owner_type' => $this->owner_type,
            'owner_id' => $this->owner_id,
            'status' => $this->status,
            'storage_disk' => $this->when($canViewStorageMetadata, $this->storage_disk),
            'storage_path' => $this->when($canViewStorageMetadata, $this->storage_path),
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size_bytes' => $this->file_size_bytes,
            'checksum_sha256' => $this->when($canViewStorageMetadata, $this->checksum_sha256),
            'download_url' => $request->user()?->can('view', $this->resource)
                ? route('documents.download', $this->resource, false)
                : null,
            'issue_date' => $this->issue_date?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'is_expired' => $this->isExpired(),
            'is_expiring_within_30_days' => $this->isExpiringWithin(30),
            'version' => $this->version,
            'is_current' => $this->is_current,
            'metadata' => $this->when(! $isTaxProof || $canViewStorageMetadata, $this->metadata),
            'approved_at' => $this->approved_at?->toISOString(),
            'company' => $this->whenLoaded('company', fn () => [
                'code' => $this->company->code,
                'name' => $this->company->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'code' => $this->category->code,
                'name' => $this->category->name,
                'owner_type' => $this->category->owner_type,
                'expiry_required' => $this->category->expiry_required,
            ]),
            'uploaded_by' => $this->whenLoaded('uploadedBy', fn () => $this->uploadedBy ? [
                'name' => $this->uploadedBy->name,
                'email' => $this->uploadedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'name' => $this->approvedBy->name,
                'email' => $this->approvedBy->email,
            ] : null),
            'employee' => $this->when(
                $this->owner_type === 'employee' && $this->relationLoaded('employeeOwner'),
                fn (): ?array => $this->employeeOwner ? [
                'id' => $this->employeeOwner->id,
                'employee_code' => $this->employeeOwner->employee_code,
                'name' => $this->employeeOwner->name,
                'department' => $this->employeeOwner->department,
                'designation' => $this->employeeOwner->designation,
                ] : null,
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function canViewStorageMetadata(Request $request, bool $isTaxProof): bool
    {
        $user = $request->user();

        if ($isTaxProof) {
            return $user !== null
                && app(\App\Domain\Payroll\Services\EmployeeTaxInputAccess::class)->canReview($user)
                && $user->can('view', $this->resource);
        }

        return $user !== null && (
            $user->hasPermission('*')
            || $user->hasPermission('documents.manage')
            || $user->hasPermission('documents.approve')
        );
    }
}
