<?php

namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\MailboxWorkspaceData;
use App\Domain\Collaboration\Services\CollaborationWorkspaceOptions;
use App\Models\CollaborationMessage;
use App\Models\User;
use App\Services\Collaboration\CollaborationService;
use App\Support\PaginationPolicy;

final class ListMailboxWorkspace
{
    public function __construct(
        private readonly CollaborationService $collaboration,
        private readonly CollaborationWorkspaceOptions $options,
        private readonly PaginationPolicy $pagination,
    ) {}

    /** @param array<string,mixed> $filters */
    public function execute(User $user, array $filters): MailboxWorkspaceData
    {
        unset(
            $filters['format'],
            $filters['compose'],
            $filters['sender_key'],
            $filters['internal_draft'],
            $filters['external_draft'],
            $filters['compose_action'],
            $filters['compose_message_id'],
        );

        $selectedMessageId = isset($filters['message_id']) ? (int) $filters['message_id'] : null;
        $listFilters = $filters;
        unset($listFilters['message_id']);

        $messages = $this->collaboration
            ->messageIndexQuery($user, $listFilters)
            ->paginate($this->pagination->workspacePerPage())
            ->appends($listFilters);

        $selectedMessage = $selectedMessageId
            ? $this->collaboration->messageIndexQuery($user, [
                'folder' => 'all',
                'message_id' => $selectedMessageId,
            ])->first()
            : $messages->getCollection()->first();

        return new MailboxWorkspaceData(
            messages: $messages,
            selectedMessage: $selectedMessage,
            filters: $filters,
            companies: $this->options->companies($user),
            projects: $this->options->projects($user),
            users: $this->options->internalUsers($user),
            folders: ['inbox' => 'Inbox', 'sent' => 'Sent', 'all' => 'All'],
            statuses: ['unread' => 'Unread', 'sent' => 'Sent', 'scheduled' => 'Scheduled', 'cancelled' => 'Cancelled', 'read' => 'Read', 'archived' => 'Archived'],
            priorities: ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'],
            canCreate: $user->can('create', CollaborationMessage::class),
        );
    }
}
