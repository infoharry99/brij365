<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatPollVote extends Model
{
    protected $fillable = ['chat_poll_id', 'chat_poll_option_id', 'voter_user_id'];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(ChatPoll::class, 'chat_poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatPollOption::class, 'chat_poll_option_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_user_id');
    }
}
