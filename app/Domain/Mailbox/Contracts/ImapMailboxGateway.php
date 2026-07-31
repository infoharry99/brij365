<?php

namespace App\Domain\Mailbox\Contracts;

use App\Models\MailboxAccount;
use App\Models\MailboxEmail;

interface ImapMailboxGateway
{
    /** @return array{folders:int,created:int,updated:int} */
    public function synchronize(MailboxAccount $account): array;

    public function test(MailboxAccount $account): void;

    public function setFlag(MailboxEmail $email, string $flag, bool $enabled): void;

    public function move(MailboxEmail $email, string $targetRemotePath): void;
}
