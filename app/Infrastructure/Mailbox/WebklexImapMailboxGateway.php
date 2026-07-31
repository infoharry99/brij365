<?php

namespace App\Infrastructure\Mailbox;

use App\Domain\Mailbox\Contracts\ImapMailboxGateway;
use App\Models\MailboxAccount;
use App\Models\MailboxEmail;
use App\Models\MailboxFolder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

final class WebklexImapMailboxGateway implements ImapMailboxGateway
{
    public function test(MailboxAccount $account): void
    {
        $client = $this->client($account);
        $client->connect();
        $client->getFolders(false);
        $client->disconnect();
    }

    public function setFlag(MailboxEmail $email, string $flag, bool $enabled): void
    {
        $client = $this->client($email->account);
        $client->connect();
        try {
            $message = $client->getFolder($email->folder->remote_path)->messages()->getMessageByUid($email->remote_uid);
            $enabled ? $message->setFlag($flag) : $message->unsetFlag($flag);
        } finally { $client->disconnect(); }
    }

    public function move(MailboxEmail $email, string $targetRemotePath): void
    {
        $client = $this->client($email->account);
        $client->connect();
        try { $client->getFolder($email->folder->remote_path)->messages()->getMessageByUid($email->remote_uid)->move($targetRemotePath); }
        finally { $client->disconnect(); }
    }

    public function synchronize(MailboxAccount $account): array
    {
        $client = $this->client($account);
        $client->connect();
        $created = $updated = $processed = 0;

        try {
            foreach ($client->getFolders(false) as $remoteFolder) {
                $status = $remoteFolder->status();
                $uidValidity = (int) ($status['uidvalidity'] ?? $status['UIDVALIDITY'] ?? 0) ?: null;
                $folder = MailboxFolder::query()->firstOrNew([
                    'mailbox_account_id' => $account->id,
                    'remote_path' => $remoteFolder->path,
                ]);

                if ($folder->exists && $folder->uid_validity && $uidValidity && (int) $folder->uid_validity !== $uidValidity) {
                    // A changed UIDVALIDITY makes every cached remote UID unsafe.
                    $folder->emails()->delete();
                    $folder->last_synced_uid = null;
                }

                $folder->fill([
                    'name' => $remoteFolder->name,
                    'delimiter' => $remoteFolder->delimiter,
                    'special_use' => $this->specialUse($remoteFolder->name),
                    'uid_validity' => $uidValidity,
                    'uid_next' => (int) ($status['uidnext'] ?? $status['UIDNEXT'] ?? 0) ?: null,
                    'is_selectable' => ! (bool) ($remoteFolder->no_select ?? false),
                    'last_synced_at' => now(),
                    'metadata' => ['has_children' => $remoteFolder->hasChildren(), 'no_select' => $remoteFolder->no_select],
                ])->save();

                if (! $folder->is_selectable) { continue; }

                // Reconcile a rolling window, not only new UIDs. Existing messages can have
                // read/star/delete flags changed by another device without receiving a new UID.
                $query = $remoteFolder->messages()->all()->setFetchBody(true)->setFetchFlags(true)->setFetchOrderDesc()
                    ->limit(max(1, (int) config('mailbox.sync_message_limit', 500)));

                $maxUid = (int) ($folder->last_synced_uid ?? 0);
                $remoteUids = [];
                foreach ($query->get() as $remoteMessage) {
                    $uid = (int) $this->scalar($remoteMessage->getUid());
                    if ($uid < 1) { continue; }
                    $remoteUids[] = $uid;
                    $payload = $this->messagePayload($account, $folder, $remoteMessage);
                    $email = MailboxEmail::query()->firstOrNew(['mailbox_folder_id' => $folder->id, 'remote_uid' => $uid]);
                    $wasNew = ! $email->exists;

                    DB::transaction(function () use ($email, $payload, $remoteMessage): void {
                        $email->fill($payload)->save();
                        $this->storeAttachments($email, $remoteMessage);
                    });

                    $wasNew ? $created++ : $updated++;
                    $maxUid = max($maxUid, $uid);
                }

                if ($remoteUids !== []) {
                    $folder->emails()->where('remote_uid', '>=', min($remoteUids))->whereNotIn('remote_uid', $remoteUids)->update(['is_deleted' => true]);
                }

                $folder->forceFill(['last_synced_uid' => $maxUid ?: null, 'last_synced_at' => now()])->save();
                $processed++;
            }
        } finally {
            $client->disconnect();
        }

        return ['folders' => $processed, 'created' => $created, 'updated' => $updated];
    }

    private function client(MailboxAccount $account): mixed
    {
        return (new ClientManager())->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'protocol' => 'imap',
            'encryption' => $account->imap_encryption ?: false,
            'validate_cert' => $account->imap_validate_cert,
            'username' => $account->username,
            'password' => $account->secret,
            'authentication' => $account->settings['imap_authentication'] ?? null,
            'timeout' => (int) config('mailbox.connection_timeout', 30),
        ]);
    }

    private function messagePayload(MailboxAccount $account, MailboxFolder $folder, mixed $message): array
    {
        $flags = collect($message->getFlags())->map(fn ($flag) => strtolower((string) $flag))->values()->all();
        $messageId = trim((string) $this->scalar($message->getMessageId()), '<> ') ?: null;
        $inReplyTo = trim((string) $this->scalar($message->getInReplyTo()), '<> ') ?: null;
        $references = array_values(array_filter(array_map(fn($value)=>trim($value,'<> '),preg_split('/\s+/', (string) $this->scalar($message->getReferences())) ?: [])));
        $subject = (string) $this->scalar($message->getSubject());
        $received = $this->date($this->scalar($message->getDate()));
        $addresses = fn (mixed $attribute): array => $this->addresses($attribute);
        $text = $message->getTextBody();
        $html = $message->getHTMLBody();
        $hash = hash('sha256', json_encode([$subject, $flags, $text, $html], JSON_THROW_ON_ERROR));

        return [
            'mailbox_account_id' => $account->id, 'mailbox_folder_id' => $folder->id,
            'remote_uid' => (int) $this->scalar($message->getUid()), 'internet_message_id' => $messageId,
            'thread_key' => ($root=($references[0]??$inReplyTo??$messageId)) ? hash('sha256',strtolower($root)) : null,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
            'subject' => $subject ?: '(No subject)', 'from_addresses' => $addresses($message->getFrom()),
            'to_addresses' => $addresses($message->getTo()), 'cc_addresses' => $addresses($message->getCc()),
            'bcc_addresses' => $addresses($message->getBcc()), 'reply_to_addresses' => $addresses($message->getReplyTo()),
            'text_body' => $text ?: null, 'html_body' => $html ?: null, 'sent_at' => $received, 'received_at' => $received,
            'size' => (int) $this->scalar($message->getSize()), 'flags' => $flags,
            'is_read' => in_array('seen', $flags, true), 'is_flagged' => in_array('flagged', $flags, true),
            'is_answered' => in_array('answered', $flags, true), 'is_draft' => in_array('draft', $flags, true),
            'is_deleted' => in_array('deleted', $flags, true), 'has_attachments' => $message->getAttachments()->count() > 0,
            'sync_hash' => $hash,
        ];
    }

    private function storeAttachments(MailboxEmail $email, mixed $message): void
    {
        foreach ($message->getAttachments() as $attachment) {
            $content = (string) $attachment->getContent();
            if ($content === '') { continue; }
            $checksum = hash('sha256', $content);
            if ($email->attachments()->where('checksum', $checksum)->exists()) { continue; }
            $name = Str::limit(basename((string) ($attachment->getName() ?: $attachment->getFilename() ?: 'attachment')), 180, '');
            $path = 'mailbox/'.$email->mailbox_account_id.'/'.$email->id.'/'.Str::uuid().'-'.$name;
            Storage::disk('local')->put($path, $content);
            $email->attachments()->create([
                'content_id' => trim((string) $attachment->getId(), '<> ') ?: null,
                'filename' => $name, 'mime_type' => (string) ($attachment->getContentType() ?: 'application/octet-stream'),
                'size' => strlen($content), 'disk' => 'local', 'path' => $path, 'checksum' => $checksum,
                'is_inline' => strtolower((string) $attachment->getDisposition()) === 'inline',
            ]);
        }
    }

    private function scalar(mixed $value): mixed { return is_object($value) && method_exists($value, 'first') ? $value->first() : $value; }
    private function date(mixed $value): ?Carbon { try { return $value ? Carbon::parse($value) : null; } catch (Throwable) { return null; } }
    private function addresses(mixed $attribute): array
    {
        $values = is_object($attribute) && method_exists($attribute, 'toArray') ? $attribute->toArray() : (array) $attribute;
        return collect($values)->map(function ($address): array {
            $email = is_object($address) ? ($address->mail ?? $address->email ?? (string) $address) : (string) $address;
            $name = is_object($address) ? ($address->personal ?? $address->name ?? null) : null;
            return ['email' => strtolower(trim((string) $email)), 'name' => $name ? trim((string) $name) : null];
        })->filter(fn (array $address): bool => filter_var($address['email'], FILTER_VALIDATE_EMAIL) !== false)->values()->all();
    }
    private function specialUse(string $name): string
    {
        $haystack = strtolower($name);
        return match (true) {
            str_contains($haystack, 'inbox') => 'inbox', str_contains($haystack, 'sent') => 'sent',
            str_contains($haystack, 'draft') => 'drafts', str_contains($haystack, 'archive') => 'archive',
            str_contains($haystack, 'trash'), str_contains($haystack, 'deleted') => 'trash',
            str_contains($haystack, 'spam'), str_contains($haystack, 'junk') => 'spam', default => 'other',
        };
    }
}
