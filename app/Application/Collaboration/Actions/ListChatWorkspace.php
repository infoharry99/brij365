<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\ChatWorkspaceData;
use App\Domain\Collaboration\Services\ChatWorkspaceConfiguration;
use App\Domain\Collaboration\Services\CollaborationWorkspaceOptions;
use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Collaboration\ChatConnectService;

final class ListChatWorkspace
{
    public function __construct(
        private readonly ChatConnectService $chat,
        private readonly ChatWorkspaceConfiguration $configuration,
        private readonly CollaborationWorkspaceOptions $workspace,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): ChatWorkspaceData
    {
        $conversations = $this->chat->conversationsFor($user, $filters);
        $selected = ($filters['list_only'] ?? false)
            ? null
            : (isset($filters['conversation_id'])
                ? $this->chat->viewableConversation($user, (int) $filters['conversation_id'])
                : $conversations->first());

        if ($selected && ! $conversations->contains('id', $selected->id)) {
            $conversations->prepend($selected);
        }
        return new ChatWorkspaceData(
            conversations: $conversations,
            selectedConversation: $selected,
            messages: $selected ? $this->chat->activeMessages($selected, $user) : collect(),
            filters: $filters,
            projects: $this->workspace->projects($user),
            users: $this->configuration->users($user),
            options: $this->configuration->for($user),
            conversationTypes: $this->conversationTypes(),
            canCreate: $user->can('create', ChatConversation::class),
        );
    }

    /** @return array<string,string> */
    private function conversationTypes(): array
    {
        return [
            'direct_message' => 'Direct Message',
            'group_chat' => 'Group Chat',
            'project_channel' => 'Project Channel',
            'unit_conversation' => 'Unit Conversation',
            'lead_conversation' => 'Lead Conversation',
            'approval_thread' => 'Approval Thread',
            'voucher_thread' => 'Voucher Thread',
            'task_thread' => 'Task Thread',
            'announcement_channel' => 'Announcement Channel',
        ];
    }
}
