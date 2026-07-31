<?php
namespace App\Application\Mailbox\Actions;
use App\Application\Mailbox\Data\UnifiedComposeData;
use App\Domain\Mailbox\Services\MailboxAttachmentInspector;
use App\Models\CollaborationMessage;
use App\Models\InternalMailboxDispatch;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
final readonly class SaveInternalMailboxDraft {
    public function __construct(private MailboxAttachmentInspector $inspector){}
    public function execute(UnifiedComposeData $data):InternalMailboxDispatch{return DB::transaction(function()use($data):InternalMailboxDispatch{
        User::query()->whereKey($data->actor->id)->lockForUpdate()->firstOrFail();
        $dispatch=InternalMailboxDispatch::query()->where('sender_user_id',$data->actor->id)->where('client_token',$data->clientToken)->lockForUpdate()->first();
        if($dispatch&&$data->lockVersion!==null&&$data->lockVersion!==$dispatch->lock_version)throw ValidationException::withMessages(['draft'=>'This draft was updated in another tab. Reload it before saving again.']);
        if ($dispatch && in_array($dispatch->state, ['sending', 'sent', 'scheduled'], true)) {
            return $dispatch->load(['recipients.user', 'attachments']);
        }
        $parent=$data->parentMessageId?CollaborationMessage::findOrFail($data->parentMessageId):null;if($parent&&!$parent->isParticipant($data->actor))throw ValidationException::withMessages(['parent_message_id'=>'You can reply only to a message thread where you are a participant.']);
        $dispatch??=new InternalMailboxDispatch(['company_id'=>$data->actor->company_id,'sender_user_id'=>$data->actor->id,'client_token'=>$data->clientToken,'thread_key'=>$parent?->thread_key??('IMT-'.strtoupper(Str::random(30)))]);
        $dispatch->fill(['project_id'=>$data->projectId,'parent_dispatch_id'=>$parent?->internal_mailbox_dispatch_id,'parent_message_id'=>$parent?->id,'subject'=>$data->subject,'body'=>$data->body,'priority'=>$data->priority,'state'=>'draft','scheduled_for'=>$data->intent==='schedule'?$data->scheduledFor:null,'last_error'=>null,'lock_version'=>(int)($dispatch->lock_version??0)+1])->save();
        $types=[];foreach($data->toUserIds as$id)$types[$id]='to';foreach($data->ccUserIds as$id)$types[$id]??='cc';foreach($data->bccUserIds as$id)$types[$id]??='bcc';$dispatch->recipients()->delete();foreach($types as$id=>$type)$dispatch->recipients()->create(['user_id'=>$id,'recipient_type'=>$type]);
        $dispatch->attachments()->whereIn('id',$data->removeAttachmentIds)->get()->each(function($file):void{Storage::disk($file->disk)->delete($file->path);$file->delete();});
        foreach($data->attachments as$file){if(!$file instanceof UploadedFile)continue;$checksum=hash_file('sha256',$file->getRealPath());if($dispatch->attachments()->where('checksum_sha256',$checksum)->exists())continue;$scan=$this->inspector->inspect($file);$path=$file->storeAs('internal-mailbox/'.$dispatch->company_id.'/'.$dispatch->id,Str::uuid().'-'.basename($file->getClientOriginalName()),'local');$dispatch->attachments()->create(['uploaded_by_user_id'=>$data->actor->id,'original_filename'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType()?:'application/octet-stream','size_bytes'=>$file->getSize(),'disk'=>'local','path'=>$path,'checksum_sha256'=>$checksum,'scan_status'=>$scan]);}
        return $dispatch->load(['recipients.user','attachments']);
    });}
}
