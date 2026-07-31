<?php
namespace App\Jobs;
use App\Application\Mailbox\Actions\SendExternalEmail;
use App\Models\MailboxOutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
class SendScheduledMailboxMessageJob implements ShouldQueue {
    use Queueable; public int $tries=3; public array $backoff=[60,300,900];
    public function __construct(public readonly int $messageId) {}
    public function middleware(): array { return [(new WithoutOverlapping('mailbox-send-'.$this->messageId))->expireAfter(900)]; }
    public function handle(SendExternalEmail $action): void { $message=MailboxOutboxMessage::with(['account','user'])->find($this->messageId); if(!$message||!in_array($message->state,['scheduled','failed'],true)||!$message->scheduled_for||$message->scheduled_for->isFuture())return; $action->execute($message->account,$message->user,['client_token'=>$message->client_token,'to'=>$message->to_addresses??[],'cc'=>$message->cc_addresses??[],'bcc'=>$message->bcc_addresses??[],'subject'=>$message->subject,'body'=>$message->text_body,'in_reply_to'=>$message->in_reply_to,'references'=>$message->references_header]); }
}
