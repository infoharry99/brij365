<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatPoll extends Model
{
    protected $fillable = [
        'chat_message_id',
        'created_by_user_id',
        'question',
        'allows_multiple',
        'anonymous',
        'closes_at',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'allows_multiple' => 'bool',
            'anonymous' => 'bool',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ChatPollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class);
    }
}
