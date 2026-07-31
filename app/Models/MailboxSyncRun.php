<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxSyncRun extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'context' => 'array']; }
    public function account(): BelongsTo { return $this->belongsTo(MailboxAccount::class, 'mailbox_account_id'); }
}
