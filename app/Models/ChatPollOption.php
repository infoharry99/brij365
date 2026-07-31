<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatPollOption extends Model
{
    protected $fillable = ['chat_poll_id', 'option_text', 'sort_order'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ChatPollVote::class);
    }
}
