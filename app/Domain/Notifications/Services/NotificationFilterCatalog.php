<?php

namespace App\Domain\Notifications\Services;

final class NotificationFilterCatalog
{
    /** @return array<string, string> */
    public function statuses(): array
    {
        return ['unread' => 'Unread', 'read' => 'Read', 'archived' => 'Archived'];
    }

    /** @return array<string, string> */
    public function severities(): array
    {
        return ['info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'critical' => 'Critical'];
    }
}
