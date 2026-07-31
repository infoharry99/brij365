<?php

namespace App\Http\Requests\Documents;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\DocumentCategory;
use App\Models\Employee;
use App\Models\ManagedDocument;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManagedDocumentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ManagedDocument::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owner_type' => ['nullable', 'required_with:owner_id', Rule::in(['project', 'booking', 'customer', 'employee'])],
            'owner_id' => ['nullable', 'integer', 'min:1'],
            'document_category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
            'status' => ['nullable', Rule::in(['submitted', 'approved', 'rejected', 'archived'])],
            'current_only' => ['nullable', 'boolean'],
            'expires_within_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            app(QueryFilterPolicy::class)->rejectUnexpected(
                $validator,
                $this->query(),
                ['owner_type', 'owner_id', 'document_category_id', 'status', 'current_only', 'expires_within_days', 'per_page', 'page'],
            );

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateCategoryScope($validator);
            $this->validateOwnerScope($validator);
        });
    }

    private function validateCategoryScope(Validator $validator): void
    {
        if (! $this->filled('document_category_id')) {
            return;
        }

        $category = DocumentCategory::find($this->integer('document_category_id'));

        if (
            $category
            && $category->company_id !== null
            && ! app(CompanyScopeService::class)->allows($this->user(), $category->company_id)
        ) {
            $validator->errors()->add('document_category_id', 'The selected document category is outside your company scope.');
        }
    }

    private function validateOwnerScope(Validator $validator): void
    {
        if (! $this->filled('owner_id')) {
            return;
        }

        $ownerType = $this->string('owner_type')->toString();
        $owner = $this->resolveOwner($ownerType, $this->integer('owner_id'));

        if (! $owner) {
            $validator->errors()->add('owner_id', 'The selected document owner does not exist.');

            return;
        }

        if (! $this->ownerBelongsToActorScope($owner)) {
            $validator->errors()->add('owner_id', 'The selected document owner is outside your company scope.');
        }
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

    private function ownerBelongsToActorScope(Model $owner): bool
    {
        $companyScope = app(CompanyScopeService::class);
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $companyId = $companyScope->companyIdFor($user);

        if ($companyId === null) {
            return true;
        }

        if ($companyId <= 0) {
            return false;
        }

        if ($owner instanceof Customer) {
            return $owner->bookings()->where('company_id', $companyId)->exists();
        }

        return $companyScope->allows($user, $owner->getAttribute('company_id'));
    }
}
