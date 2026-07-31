<?php
namespace App\Jobs;
use App\Application\Mailbox\Actions\SynchronizeMailboxAccount;
use App\Models\MailboxAccount;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
class SynchronizeMailboxAccountJob implements ShouldQueue
{
    use Queueable;
    public int $tries=3;
    public array $backoff=[60,300,900];
    public function __construct(public readonly int $accountId) {}
    public function middleware(): array { return [(new WithoutOverlapping('mailbox-sync-'.$this->accountId))->expireAfter(1800)]; }
    public function handle(SynchronizeMailboxAccount $action): void { $account=MailboxAccount::query()->whereKey($this->accountId)->where('status','!=','disabled')->where('sync_enabled',true)->first(); if($account){$action->execute($account);} }
}
