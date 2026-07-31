<?php

namespace App\Application\Mailbox\Actions;

use App\Models\MailboxAccount;
use App\Models\MailboxAccountAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class AssignMailboxAccount
{
    /** @param array{user_id:int,can_view?:bool,can_send?:bool,can_manage?:bool,is_default?:bool} $data */
    public function execute(MailboxAccount $account, User $actor, array $data): MailboxAccountAssignment
    {
        return DB::transaction(function () use ($account, $actor, $data): MailboxAccountAssignment {
            $userId = (int) $data['user_id'];
            $isOwner = $userId === (int) $account->user_id;
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                MailboxAccountAssignment::query()->where('user_id', $userId)->update(['is_default' => false]);
            }

            return MailboxAccountAssignment::query()->updateOrCreate(
                ['mailbox_account_id' => $account->id, 'user_id' => $userId],
                [
                    'assigned_by_user_id' => $actor->id,
                    'can_view' => $isOwner || (bool) ($data['can_view'] ?? true),
                    'can_send' => $isOwner || (bool) ($data['can_send'] ?? false),
                    'can_manage' => $isOwner || (bool) ($data['can_manage'] ?? false),
                    'is_default' => $isDefault,
                ],
            );
        });
    }
}
