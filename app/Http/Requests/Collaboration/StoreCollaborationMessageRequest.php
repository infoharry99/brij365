<?php

namespace App\Http\Requests\Collaboration;

use App\Models\CollaborationMessage;
use App\Models\Project;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCollaborationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CollaborationMessage::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'parent_message_id' => ['nullable', 'integer', Rule::exists('collaboration_messages', 'id')],
            'recipient_user_ids' => ['required', 'array', 'min:1', 'max:20'],
            'recipient_user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();

                if (! $actor || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);
                $explicitCompanyId = $this->filled('company_id') ? $this->integer('company_id') : null;

                if ($explicitCompanyId !== null && ! $companyScope->allows($actor, $explicitCompanyId)) {
                    $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
                }

                $project = null;
                if ($this->filled('project_id')) {
                    $project = Project::query()->whereKey($this->integer('project_id'))->first();

                    if ($project && ! $companyScope->allows($actor, $project->company_id)) {
                        $validator->errors()->add('project_id', 'The selected project is not available for your company.');
                    }

                    if ($project && $explicitCompanyId !== null && (int) $project->company_id !== $explicitCompanyId) {
                        $validator->errors()->add('company_id', 'The selected company must match the selected project company.');
                    }
                }

                $recipients = User::query()
                    ->with('role')
                    ->whereIn('id', $this->input('recipient_user_ids', []))
                    ->get();

                foreach ($recipients as $recipient) {
                    if ((int) $recipient->id === (int) $actor->id) {
                        $validator->errors()->add('recipient_user_ids', 'Messages cannot be sent to yourself.');
                    }

                    if ($recipient->status !== 'active') {
                        $validator->errors()->add('recipient_user_ids', 'All recipients must be active users.');
                    }

                    if ($recipient->hasPermission('partner.portal') || $recipient->hasPermission('buyer.view')) {
                        $validator->errors()->add('recipient_user_ids', 'Mailbox messages can be sent only to internal users.');
                    }

                    if (! $companyScope->allows($actor, $recipient->company_id)) {
                        $validator->errors()->add('recipient_user_ids', 'All recipients must belong to your company.');
                    }

                    if ($explicitCompanyId !== null && $recipient->company_id !== null && (int) $recipient->company_id !== $explicitCompanyId) {
                        $validator->errors()->add('recipient_user_ids', 'All recipients must belong to the selected company.');
                    }

                    if ($project && $recipient->company_id !== null && (int) $recipient->company_id !== (int) $project->company_id) {
                        $validator->errors()->add('recipient_user_ids', 'All recipients must belong to the selected project company.');
                    }
                }

                if ($this->filled('parent_message_id')) {
                    $parent = CollaborationMessage::query()
                        ->whereKey($this->integer('parent_message_id'))
                        ->first();

                    if ($parent && ! $parent->isParticipant($actor)) {
                        $validator->errors()->add('parent_message_id', 'You can reply only to a message thread where you are a participant.');
                    }
                }
            },
        ];
    }
}
