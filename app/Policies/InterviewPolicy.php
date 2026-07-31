<?php

namespace App\Policies;

use App\Domain\Hr\Services\ActiveInternalUserEligibility;
use App\Models\Interview;
use App\Models\User;

class InterviewPolicy
{
    public function __construct(private readonly ActiveInternalUserEligibility $internalUsers) {}

    public function submitFeedback(User $user, Interview $interview): bool
    {
        if (! $this->internalUsers->isEligible($user, $user, $interview->company_id)) {
            return false;
        }

        return collect($interview->panel_user_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->contains((int) $user->id);
    }
}
