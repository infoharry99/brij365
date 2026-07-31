<?php
namespace App\Application\Mailbox\Actions;
use App\Application\Mailbox\Data\UnifiedComposeData;
use App\Application\Mailbox\Data\UnifiedComposeResult;
use App\Models\MailboxAccount;
final readonly class ProcessUnifiedMailboxCompose {
    public function __construct(private SaveInternalMailboxDraft $internalDrafts,private DeliverInternalMailboxDispatch $internalDelivery,private SaveExternalDraft $externalDrafts,private SendExternalEmail $externalDelivery){}
    public function execute(UnifiedComposeData $data):UnifiedComposeResult {
        if($data->senderKey==='internal'){$dispatch=$this->internalDrafts->execute($data);if($data->intent==='draft')return new UnifiedComposeResult('internal','draft',$dispatch);$messages=$this->internalDelivery->execute($dispatch,$data->request);return new UnifiedComposeResult('internal',$dispatch->fresh()->state,$dispatch->fresh());}
        $accountId=(int)str($data->senderKey)->after(':')->toString();$account=MailboxAccount::findOrFail($accountId);$payload=['client_token'=>$data->clientToken,'lock_version'=>$data->lockVersion,'to'=>$data->toAddresses,'cc'=>$data->ccAddresses,'bcc'=>$data->bccAddresses,'subject'=>$data->subject,'body'=>$data->body,'scheduled_for'=>$data->scheduledFor,'attachments'=>$data->attachments,'remove_attachment_ids'=>$data->removeAttachmentIds];
        if($data->intent==='send'){$message=$this->externalDelivery->execute($account,$data->actor,$payload);return new UnifiedComposeResult('external',$message->state,$message);}
        $draft=$this->externalDrafts->execute($account,$data->actor,array_merge($payload,['state'=>$data->intent==='schedule'?'scheduled':'draft']));return new UnifiedComposeResult('external',$draft->state,$draft);
    }
}
