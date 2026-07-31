<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxAttachment extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_inline' => 'boolean']; }
    public function email(): BelongsTo { return $this->belongsTo(MailboxEmail::class, 'mailbox_email_id'); }
}
