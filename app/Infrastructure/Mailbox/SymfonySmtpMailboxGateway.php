<?php

namespace App\Infrastructure\Mailbox;

use App\Domain\Mailbox\Contracts\SmtpMailboxGateway;
use App\Models\MailboxAccount;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

final class SymfonySmtpMailboxGateway implements SmtpMailboxGateway
{
    public function test(MailboxAccount $account): void
    {
        $transport = Transport::fromDsn($this->dsn($account));
        if (method_exists($transport, 'start')) { $transport->start(); }
        if (method_exists($transport, 'stop')) { $transport->stop(); }
    }

    public function send(MailboxAccount $account, array $to, array $cc, array $bcc, string $subject, string $text, ?string $html = null, array $attachments = [], array $headers = []): string
    {
        $email = (new Email())->from(sprintf('%s <%s>', $account->name, $account->email))->to(...$to)->subject($subject)->text($text);
        if ($cc !== []) { $email->cc(...$cc); }
        if ($bcc !== []) { $email->bcc(...$bcc); }
        if ($html) { $email->html($html); }
        foreach ($headers as $name => $value) { $email->getHeaders()->addTextHeader((string) $name, (string) $value); }
        foreach ($attachments as $attachment) {
            if (($attachment['disposition'] ?? 'attachment') === 'inline') {
                $part = (new DataPart(new File($attachment['path']), $attachment['name'], $attachment['mime'] ?? null))->asInline();
                $part->setContentId($attachment['content_id'] ?: $attachment['name']);
                $email->addPart($part);
            } else {
                $email->attachFromPath($attachment['path'], $attachment['name'], $attachment['mime'] ?? null);
            }
        }
        (new Mailer(Transport::fromDsn($this->dsn($account))))->send($email);

        // SMTP delivery and the IMAP Sent folder are separate protocols. Append a
        // copy after successful delivery, but never encourage a duplicate resend
        // if the provider accepted SMTP and only the Sent-copy operation failed.
        $sentFolder = $account->folders()->where('special_use', 'sent')->first();
        if ($sentFolder) {
            try {
                $client = (new ClientManager())->make([
                    'host' => $account->imap_host, 'port' => $account->imap_port, 'protocol' => 'imap',
                    'encryption' => $account->imap_encryption ?: false, 'validate_cert' => $account->imap_validate_cert,
                    'username' => $account->username, 'password' => $account->secret,
                ]);
                $client->connect();
                try { $client->getFolder($sentFolder->remote_path)->appendMessage($email->toString(), ['\\Seen'], now()); }
                finally { $client->disconnect(); }
            } catch (Throwable $exception) {
                report($exception);
                $account->update(['last_sync_error' => 'Email was sent, but its Sent-folder copy could not be synchronized.']);
            }
        }

        return trim((string) $email->getHeaders()->get('Message-ID'), '<> ');
    }

    private function dsn(MailboxAccount $account): string
    {
        $scheme = $account->smtp_encryption === 'ssl' ? 'smtps' : 'smtp';
        $query = $account->smtp_encryption === 'tls' ? '?require_tls=true' : '';
        return sprintf('%s://%s:%s@%s:%d%s', $scheme, rawurlencode($account->username), rawurlencode($account->secret), $account->smtp_host, $account->smtp_port, $query);
    }
}
