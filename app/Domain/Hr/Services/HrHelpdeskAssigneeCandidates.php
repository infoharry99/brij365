<?php

namespace App\Domain\Hr\Services;

use App\Models\HrHelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Collection;

final class HrHelpdeskAssigneeCandidates
{
    public function __construct(private readonly ActiveInternalUserEligibility $internalUsers) {}

    /** @return Collection<int, User> */
    public function forActor(User $actor, ?HrHelpdeskTicket $ticket = null): Collection
    {
        return $this->internalUsers->forActor($actor, $ticket?->company_id);
    }

    public function isEligible(User $actor, HrHelpdeskTicket $ticket, User $candidate): bool
    {
        return $this->internalUsers->isEligible($actor, $candidate, $ticket->company_id);
    }

    public function assertEligible(User $actor, HrHelpdeskTicket $ticket, User $candidate): void
    {
        $this->internalUsers->assertEligible(
            $actor,
            $candidate,
            $ticket->company_id,
            'assigned_to_user_id',
            'The selected assignee must be an active internal user in the ticket company.',
        );
    }
}
