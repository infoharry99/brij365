<?php

namespace App\Application\Mailbox\Actions;

use App\Domain\Mailbox\Contracts\ImapMailboxGateway;
use App\Models\MailboxAccount;
use App\Models\MailboxSyncRun;
use Throwable;
use App\Services\Notifications\NotificationCenterService;

final readonly class SynchronizeMailboxAccount
{
    public function __construct(
        private ImapMailboxGateway $gateway,
        private NotificationCenterService $notifications,
        private \App\Domain\Mailbox\Services\MailboxThreadResolver $threadResolver = new \App\Domain\Mailbox\Services\MailboxThreadResolver()
    ) {}

    public function execute(MailboxAccount $account): MailboxSyncRun
    {
        $run = $account->syncRuns()->create(['status' => 'running', 'started_at' => now()]);
        try {
            $result = $this->gateway->synchronize($account);
            $this->threadResolver->repairAccountThreads($account);
            $run->update([
                'status' => 'succeeded', 'finished_at' => now(), 'folders_processed' => $result['folders'],
                'messages_created' => $result['created'], 'messages_updated' => $result['updated'],
            ]);
            $account->update(['status' => 'active', 'last_synced_at' => now(), 'last_sync_error' => null]);
            if($result['created']>0){$this->notifications->sendToUser($account->user,['category'=>'mailbox','severity'=>'info','title'=>$result['created'].' new '.str('email')->plural($result['created']),'body'=>'New messages are available in '.$account->name.'.','action_url'=>route('mailbox.external.show',$account),'payload'=>['mailbox_account_id'=>$account->id,'new_message_count'=>$result['created']]],null,$account);}
        } catch (Throwable $exception) {
            report($exception);
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_code' => class_basename($exception), 'error_message' => str($exception->getMessage())->limit(2000)]);
            $account->update(['status' => 'error', 'last_sync_error' => str($exception->getMessage())->limit(2000)]);
            throw $exception;
        }
        return $run->fresh();
    }
}
