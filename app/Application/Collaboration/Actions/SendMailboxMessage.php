<?php
namespace App\Application\Collaboration\Actions;

use App\Application\Collaboration\Data\CollaborationCommandData;
use App\Services\Collaboration\CollaborationService;
use Illuminate\Database\Eloquent\Collection;

final class SendMailboxMessage
{
    public function __construct(private readonly CollaborationService $collaboration) {}
    /** @return Collection<int,\App\Models\CollaborationMessage> */
    public function execute(CollaborationCommandData $command): Collection
    {
        return $this->collaboration->sendMessage($command->attributes, $command->actor, $command->request);
    }
}
