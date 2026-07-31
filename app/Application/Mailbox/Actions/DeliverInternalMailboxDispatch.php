<?php
namespace App\Application\Mailbox\Actions;
use App\Models\InternalMailboxDispatch;
use App\Services\Collaboration\CollaborationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final readonly class DeliverInternalMailboxDispatch {
    public function __construct(private CollaborationService $collaboration){}
    public function execute(InternalMailboxDispatch $dispatch,?\Illuminate\Http\Request $request=null):Collection{return DB::transaction(function()use($dispatch,$request):Collection{
        $locked=InternalMailboxDispatch::with(['recipients','sender'])->lockForUpdate()->findOrFail($dispatch->id);if($locked->state==='sent'||$locked->state==='scheduled')return $locked->messages()->get();if($locked->state==='sending')throw ValidationException::withMessages(['message'=>'This message is already being sent.']);if($locked->recipients->isEmpty())throw ValidationException::withMessages(['to_user_ids'=>'Select at least one recipient.']);
        $locked->update(['state'=>'sending','attempt_count'=>$locked->attempt_count+1,'last_error'=>null]);
        try{$messages=$this->collaboration->sendMessage(['company_id'=>$locked->company_id,'project_id'=>$locked->project_id,'parent_message_id'=>$locked->parent_message_id,'recipient_user_ids'=>$locked->recipients->pluck('user_id')->all(),'subject'=>$locked->subject,'body'=>$locked->body,'priority'=>$locked->priority,'scheduled_for'=>$locked->scheduled_for?->toISOString(),'metadata'=>['internal_mailbox_dispatch_id'=>$locked->id]],$locked->sender,$request);foreach($messages as$message){$recipient=$locked->recipients->firstWhere('user_id',$message->recipient_user_id);$metadata=$message->metadata??[];$metadata['recipient_type']=$recipient?->recipient_type??'to';$message->update(['internal_mailbox_dispatch_id'=>$locked->id,'metadata'=>$metadata]);}$locked->update(['state'=>$locked->scheduled_for?'scheduled':'sent','sent_at'=>$locked->scheduled_for?null:now(),'failed_at'=>null]);return $messages;}catch(\Throwable $exception){$locked->update(['state'=>'failed','failed_at'=>now(),'last_error'=>str($exception->getMessage())->limit(2000)]);throw $exception;}
    });}
}
