<?php

namespace Tests\Feature;

use App\Domain\Mailbox\Contracts\ImapMailboxGateway;
use App\Domain\Mailbox\Contracts\SmtpMailboxGateway;
use App\Models\MailboxAccount;
use App\Models\MailboxEmail;
use App\Models\MailboxOutboxMessage;
use App\Models\MailboxFolder;
use App\Jobs\SendScheduledMailboxMessageJob;
use App\Application\Mailbox\Actions\SendExternalEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalMailboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_user_can_connect_account_and_secret_is_encrypted(): void
    {
        $this->seed();
        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $this->app->bind(ImapMailboxGateway::class, fn () => new class implements ImapMailboxGateway {
            public function test(MailboxAccount $account): void {}
            public function synchronize(MailboxAccount $account): array { return ['folders'=>0,'created'=>0,'updated'=>0]; }
            public function setFlag(MailboxEmail $email,string $flag,bool $enabled): void {}
            public function move(MailboxEmail $email,string $targetRemotePath): void {}
        });
        $this->app->bind(SmtpMailboxGateway::class, fn () => new class implements SmtpMailboxGateway {
            public function test(MailboxAccount $account): void {}
            public function send(MailboxAccount $account,array $to,array $cc,array $bcc,string $subject,string $text,?string $html=null,array $attachments=[],array $headers=[]): string { return 'test'; }
        });

        $this->actingAs($user)->post(route('mailbox.accounts.store'), $this->accountPayload())->assertRedirect();
        $account = MailboxAccount::firstOrFail();
        $this->assertSame('active', $account->status);
        $this->assertSame('private-app-password', $account->secret);
        $this->assertNotSame('private-app-password', DB::table('mailbox_accounts')->value('secret'));
    }

    public function test_external_account_is_owner_scoped(): void
    {
        $this->seed();
        $owner = User::where('email', 'priya.nair@builder360.test')->firstOrFail();
        // Director has wildcard business permissions, but mailbox credentials and messages
        // remain private to the account owner.
        $other = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $account = MailboxAccount::create(array_merge($this->accountPayload(), ['company_id'=>$owner->company_id,'user_id'=>$owner->id,'status'=>'active']));
        $this->actingAs($other)->get(route('mailbox.external.show', $account))->assertForbidden();
        $this->actingAs($owner)->get(route('mailbox.external.show', $account))->assertOk();
    }

    public function test_smtp_send_uses_selected_account_and_validated_addresses(): void
    {
        $this->seed();
        $user = User::where('email', 'aditya.mehra@builder360.test')->firstOrFail();
        $account = MailboxAccount::create(array_merge($this->accountPayload(), ['company_id'=>$user->company_id,'user_id'=>$user->id,'status'=>'active']));
        $fake = new class implements SmtpMailboxGateway {
            public array $sent=[];
            public int $count=0;
            public function test(MailboxAccount $account): void {}
            public function send(MailboxAccount $account,array $to,array $cc,array $bcc,string $subject,string $text,?string $html=null,array $attachments=[],array $headers=[]): string { $this->count++; $this->sent=compact('account','to','cc','bcc','subject','text','headers'); return 'test-message-id'; }
        };
        $this->app->instance(SmtpMailboxGateway::class, $fake);
        $token=(string)Str::uuid(); $payload=['client_token'=>$token,'to'=>'one@example.com, two@example.com, ONE@example.com','subject'=>'Status','body'=>'Ready'];
        $this->actingAs($user)->post(route('mailbox.external.send', $account), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('mailbox.external.send', $account), $payload)->assertRedirect();
        $this->assertSame(['one@example.com','two@example.com'], $fake->sent['to']);
        $this->assertSame($account->id, $fake->sent['account']->id);
        $this->assertSame(1,$fake->count);
        $this->assertDatabaseHas('mailbox_outbox_messages',['client_token'=>$token,'state'=>'sent','attempt_count'=>1]);
    }

    public function test_draft_autosave_detects_concurrent_tab_conflict_and_schedule_is_persistent(): void
    {
        $this->seed(); $user=User::where('email','aditya.mehra@builder360.test')->firstOrFail();
        $account=MailboxAccount::create(array_merge($this->accountPayload(),['company_id'=>$user->company_id,'user_id'=>$user->id,'status'=>'active']));
        $token=(string)Str::uuid();
        $first=$this->actingAs($user)->postJson(route('mailbox.drafts.store',$account),['client_token'=>$token,'state'=>'draft','subject'=>'Recovered draft','body'=>'Work in progress']);
        $first->assertOk()->assertJsonPath('data.lock_version',1);
        $this->actingAs($user)->postJson(route('mailbox.drafts.store',$account),['client_token'=>$token,'lock_version'=>1,'state'=>'scheduled','to'=>'person@example.com','subject'=>'Scheduled','body'=>'Send later','scheduled_for'=>now()->addHour()->toISOString()])->assertOk()->assertJsonPath('data.state','scheduled');
        $this->actingAs($user)->postJson(route('mailbox.drafts.store',$account),['client_token'=>$token,'lock_version'=>1,'state'=>'draft','subject'=>'Stale change'])->assertUnprocessable()->assertJsonValidationErrors('draft');
        $this->assertDatabaseHas('mailbox_outbox_messages',['client_token'=>$token,'state'=>'scheduled','lock_version'=>2]);
    }

    public function test_provider_send_failure_is_recoverable_and_does_not_claim_success(): void
    {
        $this->seed(); $user=User::where('email','aditya.mehra@builder360.test')->firstOrFail();
        $account=MailboxAccount::create(array_merge($this->accountPayload(),['company_id'=>$user->company_id,'user_id'=>$user->id,'status'=>'active']));
        $this->app->bind(SmtpMailboxGateway::class,fn()=>new class implements SmtpMailboxGateway {
            public function test(MailboxAccount $account): void {}
            public function send(MailboxAccount $account,array $to,array $cc,array $bcc,string $subject,string $text,?string $html=null,array $attachments=[],array $headers=[]): string { throw new \RuntimeException('provider unavailable'); }
        });
        $token=(string)Str::uuid();
        $this->actingAs($user)->post(route('mailbox.external.send',$account),['client_token'=>$token,'to'=>'person@example.com','subject'=>'Important','body'=>'Keep this message'])->assertRedirect()->assertSessionHasErrors('send');
        $this->assertDatabaseHas('mailbox_outbox_messages',['client_token'=>$token,'state'=>'failed','attempt_count'=>1]);
        $this->assertSame('Keep this message',MailboxOutboxMessage::where('client_token',$token)->value('text_body'));
    }

    public function test_scheduled_message_sends_once_and_remote_state_failure_keeps_local_state(): void
    {
        $this->seed(); $user=User::where('email','aditya.mehra@builder360.test')->firstOrFail();
        $account=MailboxAccount::create(array_merge($this->accountPayload(),['company_id'=>$user->company_id,'user_id'=>$user->id,'status'=>'active']));
        $smtp=new class implements SmtpMailboxGateway { public int $count=0; public function test(MailboxAccount $account): void {} public function send(MailboxAccount $account,array $to,array $cc,array $bcc,string $subject,string $text,?string $html=null,array $attachments=[],array $headers=[]): string {$this->count++;return 'scheduled-id';} };
        $this->app->instance(SmtpMailboxGateway::class,$smtp);
        $scheduled=MailboxOutboxMessage::create(['mailbox_account_id'=>$account->id,'user_id'=>$user->id,'client_token'=>(string)Str::uuid(),'state'=>'scheduled','to_addresses'=>['person@example.com'],'subject'=>'Later','text_body'=>'Scheduled body','scheduled_for'=>now()->subMinute()]);
        $job=new SendScheduledMailboxMessageJob($scheduled->id); $job->handle(app(SendExternalEmail::class)); $job->handle(app(SendExternalEmail::class));
        $this->assertSame(1,$smtp->count); $this->assertSame('sent',$scheduled->fresh()->state);

        $folder=MailboxFolder::create(['mailbox_account_id'=>$account->id,'name'=>'Inbox','remote_path'=>'INBOX','special_use'=>'inbox']);
        $email=MailboxEmail::create(['mailbox_account_id'=>$account->id,'mailbox_folder_id'=>$folder->id,'remote_uid'=>1,'subject'=>'Remote state','is_read'=>false]);
        $this->app->bind(ImapMailboxGateway::class,fn()=>new class implements ImapMailboxGateway { public function test(MailboxAccount $account): void{} public function synchronize(MailboxAccount $account):array{return ['folders'=>0,'created'=>0,'updated'=>0];} public function setFlag(MailboxEmail $email,string $flag,bool $enabled):void{throw new \RuntimeException('offline');} public function move(MailboxEmail $email,string $targetRemotePath):void{throw new \RuntimeException('offline');} });
        $this->actingAs($user)->patch(route('mailbox.external.state',$email),['action'=>'read'])->assertRedirect()->assertSessionHasErrors('message_state');
        $this->assertFalse($email->fresh()->is_read);
    }

    public function test_compose_rejects_invalid_recipients_and_unsafe_attachments_before_delivery(): void
    {
        Storage::fake('local'); $this->seed(); $user=User::where('email','aditya.mehra@builder360.test')->firstOrFail();
        $account=MailboxAccount::create(array_merge($this->accountPayload(),['company_id'=>$user->company_id,'user_id'=>$user->id,'status'=>'active']));
        $this->actingAs($user)->post(route('mailbox.external.send',$account),['client_token'=>(string)Str::uuid(),'to'=>'not-an-address','subject'=>'Invalid','body'=>'Body'])->assertSessionHasErrors('to.0');
        $this->actingAs($user)->post(route('mailbox.external.send',$account),['client_token'=>(string)Str::uuid(),'to'=>'person@example.com','subject'=>'Unsafe','body'=>'Body','attachments'=>[UploadedFile::fake()->create('payload.exe',20,'application/octet-stream')]])->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('mailbox_outbox_messages',0);
    }

    private function accountPayload(): array
    {
        return ['name'=>'Work','email'=>'owner@example.com','imap_host'=>'imap.example.com','imap_port'=>993,'imap_encryption'=>'ssl','imap_validate_cert'=>1,'smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_encryption'=>'tls','username'=>'owner@example.com','secret'=>'private-app-password','sync_interval_minutes'=>5];
    }
}
