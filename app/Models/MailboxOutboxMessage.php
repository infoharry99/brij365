<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MailboxOutboxMessage extends Model {
    protected $guarded=[];
    protected function casts(): array { return ['to_addresses'=>'array','cc_addresses'=>'array','bcc_addresses'=>'array','scheduled_for'=>'datetime','sent_at'=>'datetime','failed_at'=>'datetime']; }
    public function account(): BelongsTo { return $this->belongsTo(MailboxAccount::class,'mailbox_account_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attachments(): HasMany { return $this->hasMany(MailboxOutboxAttachment::class); }
}
