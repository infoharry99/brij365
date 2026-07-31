<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversationMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_conversation_id',
        'user_id',
        'member_role',
        'can_post',
        'can_upload',
        'can_manage_members',
        'muted',
        'last_read_at',
        'archived_at',
        'removed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'can_post' => 'boolean',
            'can_upload' => 'boolean',
            'can_manage_members' => 'boolean',
            'muted' => 'boolean',
            'last_read_at' => 'datetime',
            'archived_at' => 'datetime',
            'removed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
