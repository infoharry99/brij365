<?php

namespace App\Application\Mailbox\Actions;

use App\Domain\Mailbox\Contracts\ImapMailboxGateway;
use App\Domain\Mailbox\Contracts\SmtpMailboxGateway;
use App\Models\MailboxAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class ConnectMailboxAccount
{
    public function __construct(private ImapMailboxGateway $gateway, private SmtpMailboxGateway $smtp) {}
    public function execute(User $user, array $data): MailboxAccount
    {
        return DB::transaction(function () use ($user, $data): MailboxAccount {
            $signature=$data['signature']??null; unset($data['signature']);
            $avatarFile = $data['avatar'] ?? null; unset($data['avatar']);
            $account = MailboxAccount::create(array_merge($data, [
                'company_id' => $user->company_id, 'user_id' => $user->id, 'status' => 'pending',
                'imap_validate_cert' => (bool) ($data['imap_validate_cert'] ?? false),
                'sync_interval_minutes' => (int) ($data['sync_interval_minutes'] ?? 5),
                'settings' => ['signature_text'=>$signature],
            ]));
            if ($avatarFile && method_exists($avatarFile, 'isValid') && $avatarFile->isValid()) {
                $avatarPath = $avatarFile->store('mailbox-avatars/' . $account->id, 'local');
                $settings = $account->settings ?? [];
                $settings['avatar_path'] = $avatarPath;
                $account->update(['settings' => $settings]);
            }
            $this->gateway->test($account);
            $this->smtp->test($account);
            $account->update(['status' => 'active', 'last_connection_tested_at' => now(), 'last_sync_error' => null]);
            $account->assignments()->create([
                'user_id' => $user->id,
                'assigned_by_user_id' => $user->id,
                'can_view' => true,
                'can_send' => true,
                'can_manage' => true,
                'is_default' => ! MailboxAccount::query()
                    ->where('company_id', $user->company_id)
                    ->whereKeyNot($account->id)
                    ->whereHas('assignments', fn ($query) => $query->where('user_id', $user->id)->where('is_default', true))
                    ->exists(),
            ]);
            return $account->fresh();
        });
    }
}
