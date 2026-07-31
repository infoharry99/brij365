<?php

namespace App\Policies;

use App\Models\MailboxAccount;
use App\Models\User;

class MailboxAccountPolicy
{
    public function viewAny(User $user): bool { return $user->status === 'active'; }
    public function create(User $user): bool { return $this->viewAny($user) && $user->hasPermission('collaboration.manage'); }
    public function view(User $user, MailboxAccount $account): bool { return $this->viewAny($user) && MailboxAccount::query()->whereKey($account->id)->accessibleTo($user)->exists(); }
    public function send(User $user, MailboxAccount $account): bool { return $this->viewAny($user) && MailboxAccount::query()->whereKey($account->id)->accessibleTo($user, 'send')->exists(); }
    public function update(User $user, MailboxAccount $account): bool { return $this->viewAny($user) && MailboxAccount::query()->whereKey($account->id)->accessibleTo($user, 'manage')->exists(); }
    public function delete(User $user, MailboxAccount $account): bool { return $this->update($user, $account); }
}
