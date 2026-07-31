<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\CollaborationMessage;
use App\Services\Collaboration\CollaborationService;

final class ChangeMailboxMessageCrmLink
{
    public function __construct(private readonly CollaborationService $collaboration) {}
    public function execute(CollaborationMessage $message, CollaborationCommandData $command): CollaborationMessage
    {
        return $this->collaboration->updateMessageCrmLink($message, $command->attributes, $command->actor, $command->request);
    }
}
