<?php

namespace App\Domain\Mailbox\Contracts;

use App\Models\MailboxAccount;

interface SmtpMailboxGateway
{
    public function test(MailboxAccount $account): void;

    /** @param list<string> $to @param list<string> $cc @param list<string> $bcc @param array<int,array{path:string,name:string}> $attachments */
    public function send(MailboxAccount $account, array $to, array $cc, array $bcc, string $subject, string $text, ?string $html = null, array $attachments = [], array $headers = []): string;
}
