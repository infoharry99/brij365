<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxFolder extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['is_selectable' => 'boolean', 'last_synced_at' => 'datetime', 'metadata' => 'array']; }
    public function account(): BelongsTo { return $this->belongsTo(MailboxAccount::class, 'mailbox_account_id'); }
    public function emails(): HasMany { return $this->hasMany(MailboxEmail::class); }
}
