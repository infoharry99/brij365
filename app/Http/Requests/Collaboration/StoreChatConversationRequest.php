<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Models\Project;
use App\Models\User;
use App\Services\Collaboration\ChatAccessService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ChatConversation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([
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
            'title' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'department' => ['nullable', 'string', 'max:120'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'related_type' => ['nullable', 'string', 'max:160'],
            'related_id' => ['nullable', 'integer', 'min:1'],
            'member_user_ids' => ['required', 'array', 'min:1', 'max:25'],
            'member_user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
            'body' => ['nullable', 'string', 'max:10000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high', 'critical'])],
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
                $project = $this->filled('project_id')
                    ? Project::query()->whereKey($this->integer('project_id'))->first()
                    : null;

                if ($project && ! $companyScope->allows($actor, $project->company_id)) {
                    $validator->errors()->add('project_id', 'The selected project is not available for your access.');
                }

                $memberIds = collect($this->input('member_user_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($this->input('type') === 'direct_message' && $memberIds->count() !== 1) {
                    $validator->errors()->add('member_user_ids', 'Direct messages require exactly one internal recipient.');
                }

                if ($this->input('type') !== 'direct_message' && trim((string) $this->input('title')) === '') {
                    $validator->errors()->add('title', 'A group or channel name is required.');
                }

                if ($this->input('type') === 'project_channel' && ! $project) {
                    $validator->errors()->add('project_id', 'A project is required for a project channel.');
                }

                $members = User::query()->with('role')->whereIn('id', $memberIds)->get();
                $chatAccess = app(ChatAccessService::class);

                if ($members->count() !== $memberIds->count()) {
                    $validator->errors()->add('member_user_ids', 'All selected members must exist.');
                }

                $externalRoleSlugs = ['buyer', 'channel_partner', 'executive_partner_broker'];

                foreach ($members as $member) {
                    if ((int) $member->id === (int) $actor->id) {
                        $validator->errors()->add('member_user_ids', 'You are already included as the conversation owner.');
                    }

                    if ($member->status !== 'active') {
                        $validator->errors()->add('member_user_ids', 'All members must be active internal users.');
                    }

                    $memberRoleSlug = $member->role?->slug
                        ?? str($member->role?->name ?? '')->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();

                    if (in_array($memberRoleSlug, $externalRoleSlugs, true)) {
                        $validator->errors()->add('member_user_ids', 'Chat Connect is limited to internal team members.');
                    }

                    if (! $chatAccess->canView($member)) {
                        $validator->errors()->add('member_user_ids', 'All selected members must have Chat Connect access.');
                    }

                    if (! $companyScope->allows($actor, $member->company_id)) {
                        $validator->errors()->add('member_user_ids', 'All members must be inside your company access.');
                    }

                    if ($project && $member->company_id !== null && (int) $member->company_id !== (int) $project->company_id) {
                        $validator->errors()->add('member_user_ids', 'Project conversations can include only users from the project company.');
                    }
                }
            },
        ];
    }
}
