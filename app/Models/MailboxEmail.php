<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxEmail extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'references' => 'array', 'from_addresses' => 'array', 'to_addresses' => 'array',
            'cc_addresses' => 'array', 'bcc_addresses' => 'array', 'reply_to_addresses' => 'array',
            'flags' => 'array', 'sent_at' => 'datetime', 'received_at' => 'datetime',
            'is_read' => 'boolean', 'is_flagged' => 'boolean', 'is_answered' => 'boolean',
            'is_draft' => 'boolean', 'is_deleted' => 'boolean', 'has_attachments' => 'boolean',
        ];
    }
    public function account(): BelongsTo { return $this->belongsTo(MailboxAccount::class, 'mailbox_account_id'); }
    public function folder(): BelongsTo { return $this->belongsTo(MailboxFolder::class, 'mailbox_folder_id'); }
    public function attachments(): HasMany { return $this->hasMany(MailboxAttachment::class); }
}
