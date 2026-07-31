<?php

namespace App\Application\Mailbox\Actions;

use App\Domain\Mailbox\Contracts\SmtpMailboxGateway;
use App\Models\MailboxAccount;
use App\Models\MailboxOutboxMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

final readonly class SendExternalEmail
{
    public function __construct(private SmtpMailboxGateway $gateway, private SaveExternalDraft $drafts) {}
    public function execute(MailboxAccount $account, User $user, array $data): MailboxOutboxMessage
    {
        $outbox=DB::transaction(function() use($account,$user,$data): MailboxOutboxMessage {
            MailboxAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $existing=MailboxOutboxMessage::query()->where('mailbox_account_id',$account->id)->where('client_token',$data['client_token'])->first();
            if($existing?->state==='sent') return $existing;
            if($existing?->state==='sending') throw ValidationException::withMessages(['message'=>'This email is already being sent.']);
            $scheduled=($existing?->scheduled_for)!==null;
            return $this->drafts->execute($account,$user,array_merge($data,['state'=>$scheduled?'scheduled':'draft','scheduled_for'=>$scheduled?$existing->scheduled_for:null]));
        });
        if($outbox->state==='sent') return $outbox;
        $outbox=DB::transaction(function() use($outbox): MailboxOutboxMessage {
            $locked=MailboxOutboxMessage::query()->lockForUpdate()->findOrFail($outbox->id);
            if($locked->state==='sent') return $locked;
            if($locked->state==='sending') throw ValidationException::withMessages(['message'=>'This email is already being sent.']);
            $locked->update(['state'=>'sending','attempt_count'=>$locked->attempt_count+1,'last_error'=>null]); return $locked->load('attachments','account');
        });
        if($outbox->state==='sent') return $outbox;
        try {
            $files=$outbox->attachments->map(fn($file)=>['path'=>Storage::disk($file->disk)->path($file->path),'name'=>$file->filename,'mime'=>$file->mime_type,'disposition'=>$file->disposition??'attachment','content_id'=>$file->content_id])->all();
            $inReplyToHeader = $outbox->in_reply_to ? '<' . trim($outbox->in_reply_to, '<> ') . '>' : null;
            $refsArray = array_values(array_filter(array_map(fn ($id) => '<' . trim($id, '<> ') . '>', preg_split('/\s+/', (string) $outbox->references_header) ?: [])));
            $referencesHeader = implode(' ', $refsArray) ?: null;
            $headers = array_filter(['In-Reply-To' => $inReplyToHeader, 'References' => $referencesHeader]);
            $text=$outbox->text_body??''; $signature=trim((string)($account->settings['signature_text']??'')); if($signature!==''&&!str_ends_with(trim($text),$signature))$text=rtrim($text)."\n\n-- \n".$signature;
            $html=$outbox->html_body ?: Str::markdown($text,['html_input'=>'strip','allow_unsafe_links'=>false]);
            if($signature!==''&&!str_contains(strip_tags($html),$signature)){$html.=Str::markdown("\n\n---\n".$signature,['html_input'=>'strip','allow_unsafe_links'=>false]);}
            $messageId=$this->gateway->send($account,$outbox->to_addresses??[],$outbox->cc_addresses??[],$outbox->bcc_addresses??[],$outbox->subject??'(No subject)',$text,$html,$files,$headers);
            $outbox->update(['state'=>'sent','provider_message_id'=>$messageId,'sent_at'=>now(),'failed_at'=>null,'last_error'=>null]);
        } catch(Throwable $exception) {
            $outbox->update(['state'=>'failed','failed_at'=>now(),'last_error'=>str($exception->getMessage())->limit(2000)]); report($exception); throw $exception;
        }
        return $outbox->fresh('attachments');
    }
}
