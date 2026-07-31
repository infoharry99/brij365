<?php
namespace App\Policies;
use App\Models\InternalMailboxDispatch;
use App\Models\User;
class InternalMailboxDispatchPolicy
{
    public function view(User $user, InternalMailboxDispatch $dispatch): bool
    {
        return $user->status === 'active'
            && (int) $dispatch->company_id === (int) $user->company_id
            && $dispatch->isParticipant($user);
    }

    public function update(User $user, InternalMailboxDispatch $dispatch): bool
    {
        return (int) $dispatch->sender_user_id === (int) $user->id
            && in_array($dispatch->state, ['draft', 'failed', 'scheduled'], true);
    }

    public function delete(User $user, InternalMailboxDispatch $dispatch): bool
    {
        return $this->update($user, $dispatch);
    }
}
