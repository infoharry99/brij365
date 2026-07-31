<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Models\CollaborationMessage;
use App\Services\Collaboration\CollaborationService;

final class MarkMailboxMessageRead
{
    public function __construct(private readonly CollaborationService $collaboration) {}
    public function execute(CollaborationMessage $message, CollaborationCommandData $command): CollaborationMessage
    {
        return $this->collaboration->markMessageRead($message, $command->actor, $command->request);
    }
}
