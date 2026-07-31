<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollaborationMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'chat_conversation_id',
        'internal_mailbox_dispatch_id',
        'parent_message_id',
        'sender_user_id',
        'recipient_user_id',
        'message_number',
        'thread_key',
        'subject',
        'body',
        'priority',
        'status',
        'read_at',
        'recipient_archived_at',
        'scheduled_for',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'recipient_archived_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parentMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    public function chatConversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class);
    }
    public function internalDispatch(): BelongsTo { return $this->belongsTo(InternalMailboxDispatch::class,'internal_mailbox_dispatch_id'); }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function isParticipant(User $user): bool
    {
        return (int) $this->sender_user_id === (int) $user->id
            || (int) $this->recipient_user_id === (int) $user->id;
    }
}
