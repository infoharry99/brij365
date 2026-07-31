<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChatConversationIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $search = trim((string) $this->query('q', ''));

        $this->merge([
            'q' => $search !== '' ? $search : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ChatConversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in([
                'direct_message',
                'group_chat',
                'department_channel',
                'project_channel',
                'unit_conversation',
                'lead_conversation',
                'approval_thread',
                'voucher_thread',
                'task_thread',
                'announcement_channel',
            ])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(['active', 'archived'])],
            'view' => ['nullable', 'string', Rule::in(['all', 'unread', 'mentions', 'dms', 'channels'])],
            'conversation_id' => ['nullable', 'integer', 'exists:chat_conversations,id'],
            'list_only' => ['nullable', 'boolean'],
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
                    ['type', 'project_id', 'q', 'status', 'view', 'conversation_id', 'list_only', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $companyId = Project::query()->whereKey($this->integer('project_id'))->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($this->user(), $companyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your access.');
                    }
                }
            },
        ];
    }
}
