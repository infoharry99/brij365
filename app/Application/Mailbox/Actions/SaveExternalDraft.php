<?php
namespace App\Application\Mailbox\Actions;
use App\Models\MailboxAccount;
use App\Models\MailboxOutboxMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domain\Mailbox\Services\SafeEmailHtmlSanitizer;
use App\Domain\Mailbox\Services\MailboxAttachmentInspector;
use App\Domain\Mailbox\Services\MailboxThreadResolver;
final class SaveExternalDraft {
    public function __construct(
        private readonly SafeEmailHtmlSanitizer $html,
        private readonly MailboxAttachmentInspector $inspector,
        private readonly MailboxThreadResolver $threadResolver = new MailboxThreadResolver()
    ) {}
    public function execute(MailboxAccount $account,User $user,array $data): MailboxOutboxMessage {
        return DB::transaction(function() use($account,$user,$data): MailboxOutboxMessage {
            $draft=MailboxOutboxMessage::query()->where('mailbox_account_id',$account->id)->where('client_token',$data['client_token'])->lockForUpdate()->first();
            if($draft && isset($data['lock_version']) && (int)$data['lock_version']!==(int)$draft->lock_version){throw ValidationException::withMessages(['draft'=>'This draft was updated in another tab. Reload it before saving again.']);}
            $draft??=new MailboxOutboxMessage(['mailbox_account_id'=>$account->id,'user_id'=>$user->id,'client_token'=>$data['client_token']]);
            if($draft->exists && in_array($draft->state,['sending','sent'],true)){throw ValidationException::withMessages(['draft'=>'A message being sent or already sent cannot be changed.']);}
            $safeHtml=$this->html->sanitize($data['body_html']??null);
            $plain=trim((string)($data['body']??''));
            if($plain===''&&$safeHtml!==''){$plain=trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'],["\n","\n","\n","\n","\n"],$safeHtml)),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
            $inReplyTo=$data['in_reply_to']??null;
            $referencesHeader=$data['references']??null;
            $refs=array_values(array_filter(explode(' ',(string)$referencesHeader)));
            $subject=$data['subject']??null;
            $threadKey=$this->threadResolver->resolveThreadKey(
                $account,
                $draft->provider_message_id?:$draft->client_token,
                $inReplyTo,
                $refs,
                $subject
            );
            $draft->fill(['state'=>$data['state'],'to_addresses'=>$this->addresses($data['to']??[]),'cc_addresses'=>$this->addresses($data['cc']??[]),'bcc_addresses'=>$this->addresses($data['bcc']??[]),'subject'=>$subject,'text_body'=>$plain,'html_body'=>$safeHtml?:null,'in_reply_to'=>$inReplyTo,'references_header'=>$referencesHeader,'thread_key'=>$threadKey,'scheduled_for'=>$data['state']==='scheduled'?($data['scheduled_for']??null):null,'last_error'=>null,'lock_version'=>(int)($draft->lock_version??0)+1])->save();
            $draft->attachments()->whereIn('id',$data['remove_attachment_ids']??[])->get()->each(function($file): void { Storage::disk($file->disk)->delete($file->path); $file->delete(); });
            $this->storeFiles($draft,$account,$data['attachments']??[],'attachment');
            $this->storeFiles($draft,$account,$data['inline_images']??[],'inline');
            return $draft->load('attachments');
        });
    }
    private function addresses(array $values): array { return collect($values)->map(fn($email)=>strtolower(trim($email)))->filter()->unique()->values()->all(); }
    private function storeFiles(MailboxOutboxMessage $draft,MailboxAccount $account,array $files,string $disposition): void { foreach($files as $file){if(!$file instanceof UploadedFile)continue;$checksum=hash_file('sha256',$file->getRealPath());if($draft->attachments()->where('checksum',$checksum)->where('disposition',$disposition)->exists())continue;$scanStatus=$this->inspector->inspect($file);$filename=basename($file->getClientOriginalName());$path=$file->storeAs('mailbox-outbox/'.$account->id.'/'.$draft->id,Str::uuid().'-'.$filename,'local');$draft->attachments()->create(['filename'=>$filename,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),'disk'=>'local','path'=>$path,'checksum'=>$checksum,'disposition'=>$disposition,'content_id'=>$disposition==='inline'?$filename:null,'scan_status'=>$scanStatus]);} }
}
