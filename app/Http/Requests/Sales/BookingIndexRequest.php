<?php

namespace App\Http\Requests\Sales;

use App\Models\Booking;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Booking::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'status' => ['nullable', 'string', Rule::in(['draft', 'confirmed', 'cancelled'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'per_page' => app(PaginationPolicy::class)->defaultRule(),
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['project_id', 'status', 'customer_id', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()
                        ->whereKey($this->integer('project_id'))
                        ->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if (! $this->filled('customer_id')) {
                    return;
                }

                $companyId = app(CompanyScopeService::class)->companyIdFor($user);

                if ($companyId === null) {
                    return;
                }

                $hasCompanyBooking = Booking::query()
                    ->where('company_id', $companyId)
                    ->where('customer_id', $this->integer('customer_id'))
                    ->exists();

                if (! $hasCompanyBooking) {
                    $validator->errors()->add('customer_id', 'The selected customer is not available for your company bookings.');
                }
            },
        ];
    }
}
