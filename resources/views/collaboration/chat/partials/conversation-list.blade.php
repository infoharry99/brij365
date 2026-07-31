@php
    $directConversations = $conversations->where('type', 'direct_message');
    $groupConversations = $conversations->where('type', 'group_chat');
    $channelConversations = $conversations->whereNotIn('type', ['direct_message', 'group_chat']);
@endphp

@if (filled($filterQuery['q'] ?? null))
    <div class="cc-search-summary">
        <span>
            <strong>{{ $conversations->count() }}</strong>
            {{ Str::plural('result', $conversations->count()) }} for
            &ldquo;<em>{{ $filterQuery['q'] }}</em>&rdquo;
        </span>
        <a href="{{ route('collaboration.chat.index', collect($filterQuery)->except(['q', 'conversation_id'])->all()) }}">
            Clear
        </a>
    </div>
@endif

@if ($directConversations->isNotEmpty())
    <div class="cc-section-head">
        <p class="cc-section-label">Direct Messages</p>
        <span class="cc-section-count">{{ $directConversations->count() }}</span>
    </div>
    @foreach ($directConversations as $conversation)
        @include('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ])
    @endforeach
@endif

@if ($groupConversations->isNotEmpty())
    <div class="cc-section-head">
        <p class="cc-section-label">Groups</p>
        <span class="cc-section-count">{{ $groupConversations->count() }}</span>
    </div>
    @foreach ($groupConversations as $conversation)
        @include('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ])
    @endforeach
@endif

@if ($channelConversations->isNotEmpty())
    <div class="cc-section-head">
        <p class="cc-section-label">Channels</p>
        <span class="cc-section-count">{{ $channelConversations->count() }}</span>
    </div>
    @foreach ($channelConversations as $conversation)
        @include('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ])
    @endforeach
@endif

@if ($conversations->isEmpty())
    <div class="cc-empty">
        <span class="cc-empty-icon" aria-hidden="true"><i class="fa-regular fa-comment-dots"></i></span>
        <strong>{{ filled($filterQuery['q'] ?? null) ? 'No matching conversations' : 'No conversations yet' }}</strong>
        <span>{{ filled($filterQuery['q'] ?? null) ? 'Check the spelling or clear the search to view all.' : 'Try a different filter or start a new conversation.' }}</span>
    </div>
@endif
