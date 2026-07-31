<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailboxAccountAssignment extends Model
{
    protected $table = 'mailbox_account_user';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_send' => 'boolean',
            'can_manage' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class, 'mailbox_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
