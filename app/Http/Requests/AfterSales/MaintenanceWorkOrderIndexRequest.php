<?php

namespace App\Http\Requests\AfterSales;

use App\Models\MaintenanceWorkOrder;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaintenanceWorkOrderIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MaintenanceWorkOrder::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_ticket_id' => ['nullable', 'integer', Rule::exists('service_tickets', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['nullable', 'string', Rule::in(['planned', 'scheduled', 'completed', 'cancelled'])],
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
                    ['service_ticket_id', 'assigned_to_user_id', 'status', 'per_page', 'page'],
                );

                $user = $this->user();

                if (! $user || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->validateCompanyScopedFilter($validator, 'service_ticket_id', ServiceTicket::class);
                $this->validateCompanyScopedFilter($validator, 'assigned_to_user_id', User::class);
            },
        ];
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    private function validateCompanyScopedFilter(Validator $validator, string $field, string $modelClass): void
    {
        if (! $this->filled($field)) {
            return;
        }

        $companyId = $modelClass::query()
            ->whereKey($this->integer($field))
            ->value('company_id');

        $user = $this->user();

        if (! $user || ! app(CompanyScopeService::class)->allows($user, $companyId)) {
            $validator->errors()->add($field, 'The selected record is not available for your company.');
        }
    }
}
