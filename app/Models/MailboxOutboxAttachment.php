<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MailboxOutboxAttachment extends Model {
    protected $guarded=[];
    public function message(): BelongsTo { return $this->belongsTo(MailboxOutboxMessage::class,'mailbox_outbox_message_id'); }
}
