<?php

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Collaboration\ChatAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat.conversation.{conversationId}', function (User $user, int $conversationId): bool {
    if (! app(ChatAccessService::class)->canView($user)) {
        return false;
    }

    return ChatConversation::query()
        ->whereKey($conversationId)
        ->whereHas('activeMembers', fn ($query) => $query->where('user_id', $user->id)->whereNull('removed_at'))
        ->exists();
});

Broadcast::channel('chat.user.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === (int) $userId && app(ChatAccessService::class)->canView($user);
});

Broadcast::channel('chat.presence.{conversationId}', function (User $user, int $conversationId): array|false {
    if (! app(ChatAccessService::class)->canView($user)) {
        return false;
    }

    $isMember = ChatConversation::query()
        ->whereKey($conversationId)
        ->whereHas('activeMembers', fn ($query) => $query->where('user_id', $user->id)->whereNull('removed_at'))
        ->exists();

    return $isMember ? [
        'id' => $user->id,
        'name' => $user->name,
    ] : false;
});

Broadcast::channel('tasks.user.{userId}', fn (User $user, int $userId): bool =>
    (int) $user->id === $userId && $user->can('viewAny', \App\Models\WorkTask::class)
);

Broadcast::channel('tasks.company.{companyId}', fn (User $user, int $companyId): bool =>
    $user->can('viewAny', \App\Models\WorkTask::class)
    && app(\App\Services\Security\CompanyScopeService::class)->allows($user, $companyId)
);

Broadcast::channel('calendar.user.{userId}', fn (User $user, int $userId): bool =>
    (int) $user->id === $userId && $user->can('viewAny', \App\Models\CalendarEvent::class)
);

Broadcast::channel('calendar.company.{companyId}', fn (User $user, int $companyId): bool =>
    $user->can('viewAny', \App\Models\CalendarEvent::class)
    && app(\App\Services\Security\CompanyScopeService::class)->allows($user, $companyId)
);
