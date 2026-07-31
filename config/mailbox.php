<?php

return [
    'connection_timeout' => (int) env('MAILBOX_CONNECTION_TIMEOUT', 30),
    'initial_message_limit' => (int) env('MAILBOX_INITIAL_MESSAGE_LIMIT', 250),
    'sync_message_limit' => (int) env('MAILBOX_SYNC_MESSAGE_LIMIT', 500),
    'attachment_max_kb' => (int) env('MAILBOX_ATTACHMENT_MAX_KB', 25600),
    'malware_scan_required' => (bool) env('MAILBOX_MALWARE_SCAN_REQUIRED', false),
];
