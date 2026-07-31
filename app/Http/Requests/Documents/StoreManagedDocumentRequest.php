<?php

namespace App\Http\Requests\Documents;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\ManagedDocument;
use App\Models\Project;
use App\Models\ServiceTicket;
use App\Services\Documents\DocumentFilePolicy;
use App\Services\Documents\DocumentStoragePolicy;
use App\Services\Security\CompanyScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManagedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ManagedDocument::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $filePolicy = app(DocumentFilePolicy::class);
        $fileRules = ['nullable', 'file', 'max:'.$filePolicy->maxFileSizeKb()];
        $allowedMimeTypes = $filePolicy->allowedMimeTypes();

        if ($allowedMimeTypes !== []) {
            $fileRules[] = 'mimetypes:'.implode(',', $allowedMimeTypes);
        }

        return [
            'document_category_id' => ['required', 'integer', 'exists:document_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'owner_type' => ['required', Rule::in(['project', 'booking', 'customer', 'employee'])],
            'owner_id' => ['required', 'integer', 'min:1'],
            'document_file' => $fileRules,
            'storage_disk' => ['nullable', 'string', 'max:80'],
            'storage_path' => ['required_without:document_file', 'string', 'max:1024'],
            'original_filename' => ['required_without:document_file', 'string', 'max:255'],
            'mime_type' => ['required_without:document_file', 'string', 'max:120'],
            'file_size_bytes' => ['required_without:document_file', 'integer', 'min:1'],
            'checksum_sha256' => ['required_without:document_file', 'string', 'size:64'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after:issue_date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $category = DocumentCategory::find($this->integer('document_category_id'));
            $owner = $this->resolveOwner($this->string('owner_type')->toString(), $this->integer('owner_id'));

            if (! $category || ! $owner) {
                $validator->errors()->add('owner_id', 'The selected document owner does not exist.');

                return;
            }

            if (! $category->is_active) {
                $validator->errors()->add('document_category_id', 'The selected document category is inactive.');
            }

            if ($category->owner_type !== 'global' && $category->owner_type !== $this->string('owner_type')->toString()) {
                $validator->errors()->add('document_category_id', 'The selected document category is not valid for this owner type.');
            }

            $ownerCompanyId = $this->ownerCompanyId($owner);

            if (! app(CompanyScopeService::class)->allows($this->user(), $ownerCompanyId)) {
                $validator->errors()->add('owner_id', 'The selected document owner is outside your company scope.');
            }

            if ($category->company_id !== null && $category->company_id !== $ownerCompanyId) {
                $validator->errors()->add('document_category_id', 'The selected category is outside the owner company scope.');
            }

            if ($category->expiry_required && ! $this->filled('expires_on')) {
                $validator->errors()->add('expires_on', 'An expiry date is required for this document category.');
            }

            if (! $this->hasFile('document_file')) {
                $storagePolicy = app(DocumentStoragePolicy::class);
                $storageViolations = $storagePolicy->violations(
                    $this->string('storage_disk')->toString() ?: 'local',
                    $this->string('storage_path')->toString(),
                    $this->string('original_filename')->toString(),
                );

                foreach ($storageViolations as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }

                $filePolicy = app(DocumentFilePolicy::class);
                $fileViolations = $filePolicy->violations(
                    $this->string('mime_type')->toString(),
                    $this->integer('file_size_bytes'),
                    $this->string('checksum_sha256')->toString(),
                    $this->string('original_filename')->toString(),
                    $this->string('storage_path')->toString(),
                );

                foreach ($fileViolations as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
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

    private function ownerCompanyId(Model $owner): int
    {
        if ($owner instanceof Customer) {
            $companyIds = $this->customerCompanyIds($owner);

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
    private function customerCompanyIds(Customer $customer): \Illuminate\Support\Collection
    {
        $companyScope = app(CompanyScopeService::class);
        $user = $this->user();

        $companyIds = collect()
            ->merge(Booking::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(Lead::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(ServiceTicket::query()->where('customer_id', $customer->id)->pluck('company_id'))
            ->merge(ManagedDocument::query()->where('owner_type', 'customer')->where('owner_id', $customer->id)->pluck('company_id'))
            ->filter()
            ->map(fn ($companyId): int => (int) $companyId)
            ->unique()
            ->values();

        if ($user && $companyScope->hasUnrestrictedCompanyScope($user)) {
            return $companyIds;
        }

        return $companyIds
            ->filter(fn (int $companyId): bool => $user && $companyScope->allows($user, $companyId))
            ->values();
    }
}
