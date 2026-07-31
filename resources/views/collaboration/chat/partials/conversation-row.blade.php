@php
    $latest = $conversation->latestMessage;
    $url = route('collaboration.chat.index', array_merge($filterQuery, ['conversation_id' => $conversation->id]));
    $displayTitle = $conversation->displayTitleFor(auth()->user());
    $avatarUser = $conversation->avatarUserFor(auth()->user());
    $isChannel = ! in_array($conversation->type, ['direct_message', 'group_chat'], true);
    $isSelected = $selectedConversation?->id === $conversation->id;
    $isMine = $latest && (int) $latest->sender_user_id === (int) auth()->id();
    $preview = $latest?->body
        ? ($isMine ? 'You: ' : '').str($latest->body)->squish()->limit(52)
        : str($conversation->type)->headline();
    $timeLabel = match (true) {
        ! $latest?->created_at => null,
        $latest->created_at->isToday() => $latest->created_at->format('H:i'),
        $latest->created_at->isYesterday() => 'Yesterday',
        $latest->created_at->diffInDays() < 7 => $latest->created_at->format('D'),
        default => $latest->created_at->format('d M'),
    };
@endphp
<a
    href="{{ $url }}"
    data-conversation-row
    data-conversation-id="{{ $conversation->id }}"
    data-search="{{ str($displayTitle.' '.$preview.' '.($conversation->type ?? ''))->lower() }}"
    class="cc-conv-row {{ $isSelected ? 'is-active' : '' }}"
    @if ($isSelected) aria-current="page" @endif
>
    <span class="cc-conv-avatar {{ $isChannel ? 'is-channel' : '' }}" aria-hidden="true">
        @if ($isChannel)
            <span>#</span>
        @else
            <x-ui.user-avatar
                :user="$avatarUser"
                :label="$displayTitle"
                class="cc-conv-avatar-image"
                style="display:grid;place-items:center;width:100%;height:100%;border-radius:inherit;"
            />
            @if ($avatarUser?->profile_photo_path)
                <span class="cc-dot" aria-label="Profile photo available"></span>
            @endif
        @endif
    </span>
    <span class="cc-conv-copy">
        <span class="cc-conv-name-row">
            <strong class="cc-conv-name">{{ $displayTitle }}</strong>
        </span>
        <span class="cc-conv-preview" data-conversation-preview>{{ $preview }}</span>
    </span>
    <span class="cc-conv-meta">
        @if ($timeLabel)
            <time class="cc-conv-time">{{ $timeLabel }}</time>
        @endif
        @if (($conversation->unread_count ?? 0) > 0 && ! $isSelected)
            <em class="cc-unread-badge">{{ $conversation->unread_count > 99 ? '99+' : $conversation->unread_count }}</em>
        @endif
    </span>
</a>
