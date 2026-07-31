@extends('layouts.builder360-classic')

@php
    $listQuery = collect($filters)->except(['message_id', 'page'])->filter(fn ($value) => $value !== null && $value !== '')->all();
    $currentFolder = $filters['folder'] ?? 'inbox';
    $pageMessages = $messages->getCollection();
    $unreadCount = $pageMessages->where('status', 'unread')->count();
    $scheduledCount = $pageMessages->where('status', 'scheduled')->count();
    $archivedCount = $pageMessages->where('status', 'archived')->count();
@endphp

@section('title', 'Mailbox | Builder360')

@section('content')
    <section class="b360-collaboration-screen b360-mailbox-screen" aria-label="Mailbox">
        <aside class="b360-mailbox-rail" aria-label="Mailbox folders">
            @if ($canCreateMessage)
                {{-- Internal application messaging has moved to Chat Connect. --}}
            @endif

            <a class="b360-mail-account" href="{{ route('mailbox.accounts.index') }}"><span class="b360-mail-account-dot"></span><span><strong>All accounts</strong><small>{{ auth()->user()->email }}</small></span><b>⌄</b></a>

            @if($externalAccounts->isNotEmpty())
                <nav class="b360-mail-account-links" aria-label="Connected email accounts">
                    <a class="is-active" href="{{ route('collaboration.messages.index') }}"><span class="b360-mail-account-dot"></span><span><strong>Builder360 Internal</strong><small>Employee messages</small></span></a>
                    @foreach($externalAccounts as $account)
                        <a href="{{ route('mailbox.external.show', $account) }}"><span class="b360-mail-account-dot is-external"></span><span><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></span></a>
                    @endforeach
                </nav>
            @endif

            <nav class="b360-mail-folders">
                <a class="{{ $currentFolder === 'inbox' && empty($filters['status']) ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', ['folder' => 'inbox']) }}"><span>▣</span><strong>Inbox</strong>@if($unreadCount)<em>{{ $unreadCount }}</em>@endif</a>
                <a class="{{ $currentFolder === 'sent' && empty($filters['status']) ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', ['folder' => 'sent']) }}"><span>➤</span><strong>Sent</strong></a>
                <a class="{{ ($filters['status'] ?? null) === 'scheduled' ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', ['folder' => 'sent', 'status' => 'scheduled']) }}"><span>□</span><strong>Scheduled</strong>@if($scheduledCount)<em>{{ $scheduledCount }}</em>@endif</a>
                <a class="{{ ($filters['status'] ?? null) === 'archived' ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', ['folder' => 'all', 'status' => 'archived']) }}"><span>▤</span><strong>Archived</strong>@if($archivedCount)<em>{{ $archivedCount }}</em>@endif</a>
                <a class="{{ $currentFolder === 'all' && empty($filters['status']) ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', ['folder' => 'all']) }}"><span>☰</span><strong>All mail</strong></a>
            </nav>

            @if($internalDrafts->isNotEmpty())
                @if(false)<section class="b360-internal-drafts" aria-labelledby="internal-drafts-title">
                    <h2 id="internal-drafts-title">Drafts & scheduled <span>{{ $internalDrafts->count() }}</span></h2>
                    @foreach($internalDrafts->take(6) as $draft)
                        <a href="{{ route('collaboration.messages.index', ['internal_draft' => $draft->id]) }}">
                            <i class="fa-regular fa-file-lines" aria-hidden="true"></i>
                            <span><strong>{{ $draft->subject ?: 'Untitled draft' }}</strong><small>{{ str($draft->state)->headline() }} · {{ $draft->updated_at->diffForHumans() }}</small></span>
                        </a>
                    @endforeach
                </section>@endif
            @endif

            <div class="b360-mail-rail-footer"><a href="{{ route('collaboration.messages.export', array_merge($listQuery, ['format' => 'csv'])) }}">⇩ Export CSV</a></div>
        </aside>

        <section class="b360-mail-list-pane" aria-label="Message list">
            <header class="b360-mail-list-head">
                <div class="b360-mail-list-title"><span class="b360-mail-checkbox"></span><h1>{{ $folders[$currentFolder] ?? 'Inbox' }}</h1><span>{{ number_format($messages->total()) }}</span></div>
                <a class="b360-collab-icon-btn" href="{{ route('collaboration.messages.index', $listQuery) }}" aria-label="Refresh mailbox">↻</a>
            </header>

            <form method="GET" action="{{ route('collaboration.messages.index') }}" class="b360-mail-search">
                <span>⌕</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search mail — sender, subject, body...">
                <input type="hidden" name="folder" value="{{ $currentFolder }}">
                @if (!empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            </form>

            <nav class="b360-mail-chips" aria-label="Mailbox filters">
                <a class="{{ ($filters['status'] ?? null) === 'unread' ? 'is-active' : '' }}" href="{{ route('collaboration.messages.index', array_merge($listQuery, ['folder' => $currentFolder, 'status' => 'unread'])) }}">✉ Unread</a>
                <details><summary>Priority</summary><div><a href="{{ route('collaboration.messages.index', array_merge($listQuery, ['priority' => 'high'])) }}">High</a><a href="{{ route('collaboration.messages.index', array_merge($listQuery, ['priority' => 'critical'])) }}">Critical</a><a href="{{ route('collaboration.messages.index', collect($listQuery)->except('priority')->all()) }}">Any priority</a></div></details>
                <details><summary>Project</summary><div>@foreach($projects as $project)<a href="{{ route('collaboration.messages.index', array_merge($listQuery, ['project_id' => $project->id])) }}">{{ $project->code }}</a>@endforeach<a href="{{ route('collaboration.messages.index', collect($listQuery)->except('project_id')->all()) }}">All projects</a></div></details>
            </nav>

            <div class="b360-mail-rows">
                @forelse ($messages as $message)
                    @php
                        $isMine = (int) $message->sender_user_id === (int) auth()->id();
                        $person = $isMine ? $message->recipient : $message->sender;
                        $messageUrl = route('collaboration.messages.index', array_merge($listQuery, ['message_id' => $message->id]));
                    @endphp
                    <a href="{{ $messageUrl }}" class="b360-mail-row {{ $selectedMessage?->id === $message->id ? 'is-active' : '' }} {{ $message->status === 'unread' ? 'is-unread' : '' }}">
                        <span class="b360-mail-row-check"></span>
                        <span class="b360-mail-star">☆</span>
                        <span class="b360-mail-avatar">{{ str($person?->name ?? 'System')->substr(0, 2)->upper() }}</span>
                        <span class="b360-mail-row-copy"><span><strong>{{ $person?->name ?? 'System' }}</strong><time>{{ ($message->sent_at ?? $message->created_at)?->format('h:i A') }}</time></span><b>{{ $message->subject }}</b><small>{{ str($message->body)->squish()->limit(74) }}</small><i>@if($message->project){{ $message->project->code }}@else{{ str($message->priority)->headline() }}@endif</i></span>
                    </a>
                @empty
                    <div class="b360-collab-empty b360-collab-empty-large"><span class="b360-empty-icon">✉</span><strong>This folder is empty</strong><span>Messages will appear here when they are available.</span></div>
                @endforelse
            </div>
            @if ($messages->hasPages())<div class="b360-mail-pagination">{{ $messages->links() }}</div>@endif
        </section>

        <section class="b360-mail-reading-pane" aria-label="Selected message">
            @if ($selectedMessage)
                @php $selectedPerson = (int) $selectedMessage->sender_user_id === (int) auth()->id() ? $selectedMessage->recipient : $selectedMessage->sender; @endphp
                <header class="b360-mail-reading-head"><div><span>{{ $selectedMessage->message_number }}</span><h2>{{ $selectedMessage->subject }}</h2></div><span class="blade-status-pill">{{ $statuses[$selectedMessage->status] ?? str($selectedMessage->status)->headline() }}</span></header>
                <article class="b360-mail-reading-body">
                    <div class="b360-mail-sender"><span class="b360-mail-avatar">{{ str($selectedPerson?->name ?? 'System')->substr(0, 2)->upper() }}</span><span><strong>{{ $selectedPerson?->name ?? 'System' }}</strong><small>{{ $selectedMessage->sender?->email }}</small></span><time>{{ ($selectedMessage->sent_at ?? $selectedMessage->created_at)?->format('d M Y, h:i A') }}</time></div>
                    <div class="b360-mail-message-copy">{!! nl2br(e($selectedMessage->body)) !!}</div>
                    @if($selectedMessage->project)<div class="b360-mail-linked-record"><span>Linked project</span><strong>{{ $selectedMessage->project->code }} · {{ $selectedMessage->project->name }}</strong></div>@endif
                    @if($selectedMessage->internalDispatch?->attachments?->isNotEmpty())
                        <section class="b360-mail-attachments" aria-label="Message attachments">
                            <strong>Attachments</strong>
                            @foreach($selectedMessage->internalDispatch->attachments as $attachment)
                                <span>
                                    <i class="fa-solid fa-paperclip" aria-hidden="true"></i>
                                    {{ $attachment->original_filename }}
                                    <small>{{ number_format($attachment->size_bytes / 1024, 1) }} KB</small>
                                </span>
                            @endforeach
                        </section>
                    @endif
                </article>
                <footer class="b360-mail-reading-actions">
                    <a class="blade-secondary-action" href="{{ route('collaboration.messages.index', array_merge($listQuery, ['message_id' => $selectedMessage->id, 'compose_action' => 'reply', 'compose_message_id' => $selectedMessage->id])) }}">Reply</a>
                    <a class="blade-secondary-action" href="{{ route('collaboration.messages.index', array_merge($listQuery, ['message_id' => $selectedMessage->id, 'compose_action' => 'reply_all', 'compose_message_id' => $selectedMessage->id])) }}">Reply all</a>
                    <a class="blade-secondary-action" href="{{ route('collaboration.messages.index', array_merge($listQuery, ['message_id' => $selectedMessage->id, 'compose_action' => 'forward', 'compose_message_id' => $selectedMessage->id])) }}">Forward</a>
                    @can('markRead', $selectedMessage)<form method="POST" action="{{ route('collaboration.messages.read', $selectedMessage) }}">@csrf @method('PATCH')<button type="submit" class="blade-secondary-action">Mark read</button></form>@endcan
                    @can('archive', $selectedMessage)<form method="POST" action="{{ route('collaboration.messages.archive', $selectedMessage) }}">@csrf @method('PATCH')<button type="submit" class="blade-danger-action">Archive</button></form>@endcan
                    @can('cancelScheduled', $selectedMessage)<form method="POST" action="{{ route('collaboration.messages.cancel-scheduled', $selectedMessage) }}">@csrf @method('PATCH')<input type="hidden" name="reason" value="Cancelled from mailbox"><button type="submit" class="blade-danger-action">Cancel scheduled</button></form>@endcan
                </footer>
            @else
                <div class="b360-collab-empty b360-collab-empty-large"><span class="b360-empty-icon">✉</span><strong>Select a message</strong><span>Choose a message from the list to read the conversation and linked project details.</span></div>
            @endif
        </section>
    </section>
@endsection
