<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class MailboxAccount extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'imap_validate_cert' => 'boolean',
            'sync_enabled' => 'boolean',
            'last_connection_tested_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function folders(): HasMany { return $this->hasMany(MailboxFolder::class); }
    public function emails(): HasMany { return $this->hasMany(MailboxEmail::class); }
    public function syncRuns(): HasMany { return $this->hasMany(MailboxSyncRun::class); }
    public function outboxMessages(): HasMany { return $this->hasMany(MailboxOutboxMessage::class); }
    public function assignments(): HasMany { return $this->hasMany(MailboxAccountAssignment::class); }
    public function assignedUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'mailbox_account_user')->withPivot(['can_view','can_send','can_manage','is_default'])->withTimestamps(); }

    public function scopeAccessibleTo(Builder $query, User $user, string $capability = 'view'): Builder
    {
        $column = match ($capability) {
            'send' => 'can_send',
            'manage' => 'can_manage',
            default => 'can_view',
        };

        return $query
            ->where('company_id', $user->company_id)
            ->where(function (Builder $access) use ($user, $column): void {
                $access->where('user_id', $user->id)
                    ->orWhereHas('assignments', fn (Builder $assignment): Builder => $assignment
                        ->where('user_id', $user->id)
                        ->where($column, true));
            });
    }

    public function assignmentFor(User $user): ?MailboxAccountAssignment
    {
        return $this->assignments()->where('user_id', $user->id)->first();
    }
}
