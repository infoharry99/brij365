<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'owner_user_id',
        'conversation_key',
        'direct_pair_key',
        'type',
        'title',
        'description',
        'visibility',
        'department',
        'related_type',
        'related_id',
        'status',
        'last_message_at',
        'settings',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'settings' => 'array',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatConversationMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->whereNull('removed_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CollaborationMessage::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }

    public function isMember(User $user): bool
    {
        return $this->activeMembers()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function membershipFor(User $user): ?ChatConversationMember
    {
        return $this->activeMembers()
            ->where('user_id', $user->id)
            ->first();
    }

    public function isSensitive(): bool
    {
        return in_array($this->type, ['approval_thread', 'voucher_thread'], true)
            || in_array($this->department, ['finance', 'hr', 'management', 'approvals', 'vouchers'], true);
    }

    public function displayTitleFor(User $user): string
    {
        if ($this->type !== 'direct_message') {
            return $this->title;
        }

        $members = $this->relationLoaded('activeMembers')
            ? $this->activeMembers
            : $this->activeMembers()->with('user')->get();

        return $members
            ->first(fn (ChatConversationMember $member): bool => (int) $member->user_id !== (int) $user->id)
            ?->user
            ?->name ?? $this->title;
    }

    public function avatarUserFor(User $user): ?User
    {
        if ($this->type !== 'direct_message') {
            return null;
        }

        $members = $this->relationLoaded('activeMembers')
            ? $this->activeMembers
            : $this->activeMembers()->with('user')->get();

        return $members
            ->first(fn (ChatConversationMember $member): bool => (int) $member->user_id !== (int) $user->id)
            ?->user;
    }
}
