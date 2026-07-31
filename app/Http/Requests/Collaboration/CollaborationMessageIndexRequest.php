<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CollaborationMessage;
use App\Models\Project;
use App\Services\Security\CompanyScopeService;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CollaborationMessageIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CollaborationMessage::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'folder' => ['nullable', 'string', Rule::in(['inbox', 'sent', 'all'])],
            'status' => ['nullable', 'string', Rule::in(['unread', 'read', 'archived', 'scheduled', 'cancelled'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'message_id' => ['nullable', 'integer', 'exists:collaboration_messages,id'],
            'thread_key' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
            'format' => ['nullable', 'string', Rule::in(['csv', 'pdf'])],
            'compose' => ['nullable', 'boolean'],
            'sender_key' => ['nullable', 'string', 'max:80'],
            'internal_draft' => ['nullable', 'integer', 'exists:internal_mailbox_dispatches,id'],
            'external_draft' => ['nullable', 'integer', 'exists:mailbox_outbox_messages,id'],
            'compose_action' => ['nullable', 'string', Rule::in(['reply', 'reply_all', 'forward'])],
            'compose_message_id' => ['nullable', 'integer', 'exists:collaboration_messages,id'],
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
                    ['folder', 'status', 'priority', 'project_id', 'message_id', 'thread_key', 'q', 'format', 'compose', 'sender_key', 'internal_draft', 'external_draft', 'compose_action', 'compose_message_id', 'page'],
                );

                if ($validator->errors()->isNotEmpty() || ! $this->user()) {
                    return;
                }

                if ($this->filled('project_id')) {
                    $projectCompanyId = Project::query()
                        ->whereKey($this->integer('project_id'))
                        ->value('company_id');

                    if (! app(CompanyScopeService::class)->allows($this->user(), $projectCompanyId)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }
                }

                if ($this->filled('message_id')) {
                    $message = CollaborationMessage::query()
                        ->whereKey($this->integer('message_id'))
                        ->first();

                    if (
                        ! $message
                        || (
                            (int) $message->sender_user_id !== (int) $this->user()->id
                            && (int) $message->recipient_user_id !== (int) $this->user()->id
                        )
                        || ! app(CompanyScopeService::class)->allows($this->user(), $message->company_id)
                    ) {
                        $validator->errors()->add('message_id', 'The selected mailbox message is not available to your user scope.');
                    }
                }
            },
        ];
    }
}
