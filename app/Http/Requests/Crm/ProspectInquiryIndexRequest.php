<?php

namespace App\Http\Requests\Crm;

use App\Models\Project;
use App\Models\ProspectInquiry;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProspectInquiryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProspectInquiry::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in([
                ProspectInquiry::STATUS_NEW,
                ProspectInquiry::STATUS_DUPLICATE,
                ProspectInquiry::STATUS_ASSIGNED,
                ProspectInquiry::STATUS_CONTACTED,
                ProspectInquiry::STATUS_QUALIFIED,
                ProspectInquiry::STATUS_CONVERTED,
                ProspectInquiry::STATUS_CLOSED_UNQUALIFIED,
                ProspectInquiry::STATUS_CLOSED_DUPLICATE,
            ])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'assigned_to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'source' => ['nullable', 'string', 'max:80'],
            'channel' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
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
                    [
                        'status',
                        'project_id',
                        'assigned_to_user_id',
                        'source',
                        'channel',
                        'q',
                        'created_from',
                        'created_to',
                        'per_page',
                        'page',
                    ],
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

                if ($this->filled('assigned_to_user_id')) {
                    $assignedUserCompanyId = User::query()
                        ->whereKey($this->integer('assigned_to_user_id'))
                        ->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($user, $assignedUserCompanyId)) {
                        $validator->errors()->add('assigned_to_user_id', 'The selected assignee is not available for your company.');
                    }
                }
            },
        ];
    }
}
