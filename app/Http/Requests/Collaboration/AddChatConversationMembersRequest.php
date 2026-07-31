<?php

namespace App\Http\Requests\Collaboration;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Collaboration\ChatAccessService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AddChatConversationMembersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('chatConversation');

        if (! $conversation instanceof ChatConversation || $conversation->type === 'direct_message') {
            return false;
        }

        return $this->user()?->can('manageMembers', $conversation) ?? false;
    }

    public function rules(): array
    {
        return [
            'member_user_ids' => ['required', 'array', 'min:1', 'max:25'],
            'member_user_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $actor = $this->user();
                $conversation = $this->route('chatConversation');

                if (! $actor || ! $conversation instanceof ChatConversation || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $companyScope = app(CompanyScopeService::class);
                $chatAccess = app(ChatAccessService::class);
                $externalRoleSlugs = ['buyer', 'channel_partner', 'executive_partner_broker'];

                $memberIds = collect($this->input('member_user_ids', []))
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                $members = User::query()->with('role')->whereIn('id', $memberIds)->get();

                if ($members->count() !== $memberIds->count()) {
                    $validator->errors()->add('member_user_ids', 'All selected members must exist.');
                }

                foreach ($members as $member) {
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

                    if ($conversation->project_id && $member->company_id !== null && (int) $member->company_id !== (int) $conversation->project?->company_id) {
                        $validator->errors()->add('member_user_ids', 'Project conversations can include only users from the project company.');
                    }
                }
            },
        ];
    }
}
