@extends('layouts.builder360-classic')

@php
    $membership = $selectedConversation?->activeMembers?->firstWhere('user_id', auth()->id());
    $canPost = $selectedConversation && ($membership?->can_post ?? false) && ($chatOptions['can_post'] ?? false);
    $allowedTypes = collect($conversationTypes)->filter(function ($label, $type) use ($chatOptions) {
        return match ($type) {
            'direct_message' => (bool) ($chatOptions['can_create_dm'] ?? false),
            'group_chat' => (bool) ($chatOptions['can_create_group'] ?? false),
            default => (bool) ($chatOptions['can_create_channel'] ?? false),
        };
    });
    $filterQuery = collect($filters)
        ->except(['conversation_id', 'list_only'])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->all();
    $searchBaseQuery = collect($filterQuery)->except(['view', 'type'])->all();
    $clearSearchQuery = collect($filterQuery)->except(['q'])->all();
    $activeType = $filters['type'] ?? null;
    $activeView = $filters['view'] ?? 'all';
    $selectedDisplayTitle = $selectedConversation?->displayTitleFor(auth()->user());
    $selectedAvatarUser = $selectedConversation?->avatarUserFor(auth()->user());
@endphp

@section('title', 'Chat Connect | Builder360')
<style>
    /*
        ═══════════════════════════════════════════════════════════════
        CHAT RAIL — Arctic Clarity (White + Light Blue)
        Chat-only presentation rules. Keep this block outside Blade stack directives.
        Only visual changes — no functional classes touched.
        ═══════════════════════════════════════════════════════════════
        */

        /* ── Rail wrapper ── */
        .cc-rail {
        background: #FFFFFF;
        border-right: 1px solid #E2E8F2;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        }

        /* Blue top accent line (matches sidebar) */
        .cc-rail::before {
        content: '';
        display: block;
        height: 3px;
        flex-shrink: 0;
        background: linear-gradient(90deg, #F6740C, #60A5FA);
        }

        /* ── Search bar ── */
        .cc-rail-search {
        margin: 10px 10px 6px;
        flex-shrink: 0;
        }
        .cc-rail-search-inner {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #F7F9FC;
        border: 1px solid #E2E8F2;
        border-radius: 10px;
        padding: 8px 12px;
        transition: border-color .15s, box-shadow .15s;
        }
        .cc-rail-search-inner:focus-within {
        border-color: #F6740C;
        box-shadow: 0 0 0 3px rgba(37,99,235,.10);
        background: #fff;
        }
        .cc-rail-search-inner i { color: #94A3B8; font-size: 12px; flex-shrink: 0; }
        .cc-rail-search-inner input {
        flex: 1; border: none; outline: none;
        background: transparent;
        font-size: 13px; color: #0F172A; font-family: inherit;
        }
        .cc-rail-search-inner input::placeholder { color: #94A3B8; }

        /* ── Filter tabs ── */
        .cc-rail-tabs {
        display: flex;
        gap: 3px;
        padding: 0 10px 8px;
        flex-shrink: 0;
        overflow-x: auto;
        scrollbar-width: none;
        }
        .cc-rail-tabs::-webkit-scrollbar { display: none; }
        .cc-rail-tab {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 11px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        color: #64748B;
        text-decoration: none;
        white-space: nowrap;
        border: 1px solid #E2E8F2;
        background: transparent;
        transition: all .14s;
        }
        .cc-rail-tab:hover {
        background: #EEF4FF;
        border-color: #BFDBFE;
        color: #F6740C;
        text-decoration: none;
        }
        .cc-rail-tab.is-active {
        background: #EEF4FF;
        border-color: #BFDBFE;
        color: #F6740C;
        font-weight: 600;
        }
        .cc-tab-pill {
        background: #EF4444;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        border-radius: 10px;
        padding: 1px 5px;
        min-width: 16px;
        text-align: center;
        }

        /* ── Section headers ── */
        .cc-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 14px 4px;
        flex-shrink: 0;
        }
        .cc-section-label {
        margin: 0;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .09em;
        text-transform: uppercase;
        color: #94A3B8;
        user-select: none;
        }
        .cc-section-count {
        font-size: 10px;
        font-weight: 700;
        color: #94A3B8;
        background: #F7F9FC;
        border: 1px solid #E2E8F2;
        border-radius: 4px;
        padding: 1px 6px;
        }

        /* ── Search summary ── */
        .cc-search-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 8px 14px;
        background: #EEF4FF;
        border-bottom: 1px solid #BFDBFE;
        font-size: 12px;
        color: #475569;
        }
        .cc-search-summary strong { color: #F6740C; }
        .cc-search-summary em { color: #0F172A; font-style: normal; }
        .cc-search-summary a {
        color: #F6740C;
        font-weight: 600;
        text-decoration: none;
        font-size: 11px;
        white-space: nowrap;
        }
        .cc-search-summary a:hover { text-decoration: underline; }

        /* ── Conversation list scroll area ── */
        .cc-conv-list {
        flex: 1;
        overflow-y: auto;
        padding: 4px 6px 12px;
        }
        .cc-conv-list::-webkit-scrollbar { width: 3px; }
        .cc-conv-list::-webkit-scrollbar-track { background: transparent; }
        .cc-conv-list::-webkit-scrollbar-thumb { background: #E2E8F2; border-radius: 3px; }
        .cc-conv-list::-webkit-scrollbar-thumb:hover { background: #BFDBFE; }

        /* ── Conversation row ── */
        .cc-conv-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        margin: 1px 0;
        border-radius: 10px;
        text-decoration: none;
        color: #0F172A;
        cursor: pointer;
        position: relative;
        transition: background .12s;
        }
        .cc-conv-row:hover {
        background: #F0F4FA;
        text-decoration: none;
        }
        .cc-conv-row.is-active {
        background: #EEF4FF;
        color: #f78020;
        }
        /* Blue left accent bar on active */
        .cc-conv-row.is-active::before {
        content: '';
        position: absolute;
        left: -6px;
        top: 20%;
        bottom: 20%;
        width: 3px;
        background: #F6740C;
        border-radius: 0 3px 3px 0;
        }

        /* ── Avatar ── */
        .cc-conv-avatar {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: #F6740C;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 700;
        flex-shrink: 0;
        color: #fff;
        text-transform: uppercase;
        position: relative;
        letter-spacing: -.02em;
        overflow: hidden;
        }
        .cc-conv-avatar.is-channel {
        background: #F0F4FA;
        border: 1px solid #E2E8F2;
        color: #64748B;
        font-size: 18px;
        font-weight: 400;
        }

        /* Online dot */
        .cc-dot {
        position: absolute;
        bottom: 0; right: 0;
        width: 10px; height: 10px;
        border-radius: 50%;
        background: #22C55E;
        border: 2px solid #fff;
        }

        /* ── Row copy ── */
        .cc-conv-copy { flex: 1; min-width: 0; }
        .cc-conv-name-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 2px;
        }
        .cc-conv-name {
        font-size: 13px;
        font-weight: 600;
        color: #0F172A;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        }
        .cc-conv-row.is-active .cc-conv-name { color: #f78020; }
        .cc-conv-preview {
        display: block;
        font-size: 12px;
        color: #94A3B8;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4;
        }

        /* ── Meta (time + badge) ── */
        .cc-conv-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
        }
        .cc-conv-time {
        font-size: 11px;
        color: #94A3B8;
        white-space: nowrap;
        }
        .cc-unread-badge {
        background: #F6740C;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        border-radius: 10px;
        padding: 2px 6px;
        min-width: 18px;
        text-align: center;
        line-height: 1.5;
        }

        /* ── Empty state ── */
        .cc-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        padding: 32px 16px;
        text-align: center;
        }
        .cc-empty-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: #EEF4FF;
        border: 1px solid #BFDBFE;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #F6740C;
        font-size: 18px;
        margin-bottom: 4px;
        }
        .cc-empty strong { font-size: 13px; font-weight: 600; color: #0F172A; }
        .b360-chat-member-picker { overflow-y: auto !important; max-height: 260px !important; min-height: 80px; }
        [hidden], .b360-chat-member-option[hidden], [data-mention-option][hidden], [data-conversation-row][hidden], [data-people-option][hidden] { display: none !important; }
        .b360-group-manage-details[open] .b360-chat-create-panel {
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            position: fixed !important;
            z-index: 100 !important;
            width: min(560px, calc(100vw - 32px)) !important;
            max-height: min(640px, calc(100vh - 48px)) !important;
            border-radius: 16px !important;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.22) !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            background: #fff !important;
        }
        .b360-group-manage-details .b360-chat-member-option {
            grid-template-columns: auto minmax(0, 1fr) auto !important;
            box-sizing: border-box !important;
            width: 100% !important;
        }
</style>
@section('content')
    <section
        class="b360-collaboration-screen b360-chat-screen {{ $selectedConversation ? 'has-conversation' : 'no-conversation' }}"
        x-data="chatRealtime"
        data-conversation-id="{{ $selectedConversation?->id ?? '' }}"
        data-message-count="{{ $chatMessages->count() }}"
        data-latest-message-id="{{ $chatMessages->last()?->id ?? '' }}"
        data-messages-url="{{ $selectedConversation ? route('collaboration.chat.conversations.messages.index', $selectedConversation) : '' }}"
        data-timeline-url="{{ $selectedConversation ? route('collaboration.chat.conversations.timeline', $selectedConversation) : '' }}"
        data-conversations-url="{{ route('collaboration.chat.conversations.index', array_merge($filterQuery, ['conversation_id' => $selectedConversation?->id])) }}"
        data-sidebar-url="{{ route('collaboration.chat.sidebar', array_merge($filterQuery, ['conversation_id' => $selectedConversation?->id])) }}"
        data-read-url="{{ $selectedConversation ? route('collaboration.chat.conversations.read', $selectedConversation) : '' }}"
        data-user-id="{{ auth()->id() }}"
        data-mention-count="{{ $selectedConversation?->activeMembers?->where('user_id', '!=', auth()->id())->count() ?? 0 }}"
        data-selected-unread="{{ (int) ($selectedConversation?->unread_count ?? 0) }}"
        data-realtime-enabled="{{ data_get($chatOptions, 'reverb.enabled') ? '1' : '0' }}"
        data-reverb-key="{{ data_get($chatOptions, 'reverb.key') }}"
        data-reverb-host="{{ data_get($chatOptions, 'reverb.host') }}"
        data-reverb-port="{{ data_get($chatOptions, 'reverb.port') }}"
        data-reverb-scheme="{{ data_get($chatOptions, 'reverb.scheme') }}"
    >
        <aside class="b360-collab-rail b360-chat-rail" aria-label="Conversations">
            <header class="b360-collab-rail-head">
                <div class="b360-collab-title">
                    <span class="b360-collab-logo" aria-hidden="true"><i class="fa-regular fa-comment"></i></span>
                    <h1>Chat Connect</h1>
                </div>
                @if ($canCreateChat && $allowedTypes->isNotEmpty())
                    <details class="b360-collab-create" x-data="peopleFilter" data-initial-type="{{ old('type', 'direct_message') }}" data-people-count="{{ $users->count() }}" @if ($errors->hasAny(['type', 'title', 'project_id', 'member_user_ids', 'member_user_ids.*', 'body'])) open @endif>
                        <summary class="b360-collab-icon-btn" aria-label="New conversation"><i class="fa-solid fa-plus"></i></summary>
                        <div class="b360-collab-popover b360-chat-create-panel" role="dialog" aria-modal="true" aria-labelledby="new-conversation-title">
                            <div class="b360-popover-head">
                                <div><h2 id="new-conversation-title">New conversation</h2><p>Start a direct message, group, or work channel.</p></div>
                                <button type="button" class="b360-chat-create-close" x-on:click="closePeoplePanel" aria-label="Close new conversation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                            </div>
                            <form method="POST" action="{{ route('collaboration.chat.conversations.store') }}" class="b360-chat-form">
                                @csrf
                                @if ($errors->hasAny(['type', 'title', 'project_id', 'member_user_ids', 'member_user_ids.*', 'body']))
                                    <div class="b360-chat-create-errors" role="alert"><strong>Please review the conversation details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                                @endif
                                <label><span>Conversation type</span><select name="type" x-model="conversationType" x-on:change="changeConversationType" required>
                                    @foreach ($allowedTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', 'direct_message') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select></label>
                                <label x-show="requiresTitle" x-cloak><span>Conversation title</span><input name="title" maxlength="160" value="{{ old('title') }}" placeholder="Example: Finance handover" x-bind:required="requiresTitle"></label>
                                <label x-show="requiresProject" x-cloak><span>Project</span><select name="project_id" x-bind:required="requiresProject"><option value="">Select project</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->code }} · {{ $project->name }}</option>
                                    @endforeach
                                </select></label>
                                <label class="b360-chat-member-search"><span>Find internal employees</span><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" x-model.debounce.150ms="peopleQuery" placeholder="Search name, email or role"></label>
                                <section class="b360-chat-member-picker" aria-labelledby="chat-member-label">
                                    <div class="b360-chat-member-picker-head"><div><span id="chat-member-label" x-text="memberFieldLabel"></span><small x-text="selectionHelp"></small></div><span class="b360-chat-member-count" x-text="selectedPeopleCount"></span></div>
                                    <div class="b360-chat-member-options">
                                    @foreach ($users as $user)
                                        <label
                                            class="b360-chat-member-option"
                                            data-people-option
                                            data-search="{{ str($user->name.' '.$user->email.' '.($user->role?->name ?? ''))->lower() }}"
                                        >
                                            <input
                                                type="checkbox"
                                                name="member_user_ids[]"
                                                value="{{ $user->id }}"
                                                x-on:change="togglePerson"
                                                @checked(in_array((string) $user->id, array_map('strval', old('member_user_ids', [])), true))
                                            >
                                    
                                            <span>
                                                {{ $user->name }}
                                                <small>{{ $user->role?->name ?? $user->email }}</small>
                                            </span>
                                    
                                            <i class="fa-solid fa-check" aria-hidden="true"></i>
                                        </label>
                                    @endforeach
                                        <p class="b360-chat-member-empty" x-show="noPeopleMatches" x-cloak>No matching employees.</p>
                                    </div>
                                </section>
                                <label><span>First message <small>(optional)</small></span><textarea name="body" maxlength="10000" placeholder="Write the first message">{{ old('body') }}</textarea></label>
                                <div class="b360-chat-create-actions"><button type="button" class="b360-secondary-btn" x-on:click="closePeoplePanel">Cancel</button><button class="b360-primary-btn" type="submit" x-bind:disabled="!canCreateConversation">Create conversation</button></div>
                            </form>
                        </div>
                    </details>
                @endif
            </header>

            <form method="GET" action="{{ route('collaboration.chat.index') }}" class="b360-collab-search {{ filled($filters['q'] ?? null) ? 'has-query' : '' }}" role="search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input
                    type="search"
                    name="q"
                    x-ref="sidebarSearch"
                    value="{{ $filters['q'] ?? '' }}"
                    placeholder="Search messages, people, files..."
                    aria-label="Search conversations"
                    x-on:input.debounce.100ms="filterConversations"
                >
                @if (filled($filters['q'] ?? null))
                    <a class="b360-chat-search-clear" href="{{ route('collaboration.chat.index', $clearSearchQuery) }}" aria-label="Clear conversation search"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
                @endif
                <button type="submit" aria-label="Search conversations"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                @foreach (collect($filterQuery)->only(['type', 'view', 'project_id', 'status']) as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
            </form>

            <nav class="b360-collab-tabs" aria-label="Conversation filters">
                <a class="{{ $activeView === 'all' && $activeType === null ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', $searchBaseQuery) }}">All</a>
                <a class="{{ $activeView === 'unread' ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', array_merge($searchBaseQuery, ['view' => 'unread'])) }}">Unread</a>
                <a class="{{ $activeView === 'mentions' ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', array_merge($searchBaseQuery, ['view' => 'mentions'])) }}">Mentions</a>
                <a class="{{ $activeView === 'dms' || $activeType === 'direct_message' ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', array_merge($searchBaseQuery, ['view' => 'dms'])) }}">DMs</a>
                <a class="{{ $activeType === 'group_chat' ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', array_merge($searchBaseQuery, ['type' => 'group_chat'])) }}">Groups</a>
                <a class="{{ $activeView === 'channels' ? 'is-active' : '' }}" href="{{ route('collaboration.chat.index', array_merge($searchBaseQuery, ['view' => 'channels'])) }}">Channels</a>
            </nav>

            <nav class="b360-collab-list b360-conversation-list" aria-label="Available conversations" x-ref="conversationList">
                @include('collaboration.chat.partials.conversation-list', [
                    'conversations' => $conversations,
                    'selectedConversation' => $selectedConversation,
                    'filterQuery' => $filterQuery,
                ])
            </nav>
        </aside>

        <section class="b360-collab-main b360-chat-main" aria-label="Selected conversation">
            @if ($selectedConversation)
                <header class="b360-thread-head">
                    <a class="b360-chat-mobile-back" href="{{ route('collaboration.chat.index', array_merge($filterQuery, ['list_only' => 1])) }}" aria-label="Back to conversations"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                    <x-ui.user-avatar :user="$selectedAvatarUser" :label="$selectedDisplayTitle" class="b360-thread-avatar" />
                    <div class="b360-thread-title">
                        <h2>{{ $selectedDisplayTitle }}</h2>
                        <p>{{ str($selectedConversation->type)->headline() }} · {{ $selectedConversation->activeMembers?->count() ?? 0 }} members
                            @if($selectedConversation->project) · {{ $selectedConversation->project->name }} @endif
                        </p>
                        <small class="b360-chat-connection" x-text="connectionLabel" x-bind:class="connectionClass"></small>
                    </div>
                    <div class="b360-thread-actions">
                        @if($selectedConversation->type !== 'direct_message')
                            <details class="b360-collab-create b360-group-manage-details" x-data="{ groupTab: 'members' }">
                                <summary class="b360-collab-icon-btn" title="Group settings & members" aria-label="Group settings & members">
                                    <i class="fa-solid fa-users-gear"></i>
                                </summary>
                                <div class="b360-collab-popover b360-chat-create-panel" role="dialog" aria-modal="true" aria-labelledby="manage-group-title" style="width: min(580px, calc(100vw - 32px)); max-height: min(680px, calc(100vh - 40px)); overflow: hidden; display: flex; flex-direction: column;">
                                    
                                    <div class="b360-popover-head" style="padding: 18px 24px; border-bottom: 1px solid #E2E8F2; flex-shrink: 0; background: #fff;">
                                        <div>
                                            <h2 id="manage-group-title" style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0;">{{ $selectedDisplayTitle }}</h2>
                                            <p style="font-size: 12px; color: #64748B; margin: 3px 0 0;">{{ str($selectedConversation->type)->headline() }} · Manage settings & members</p>
                                        </div>
                                        <button type="button" class="b360-chat-create-close" x-on:click="$el.closest('details').removeAttribute('open')" aria-label="Close group settings"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                    </div>

                                    <div style="flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; box-sizing: border-box;">
                                        
                                        <nav style="display: flex; gap: 8px; border-bottom: 1px solid #E2E8F2; padding-bottom: 12px; flex-shrink: 0;" aria-label="Group tabs">
                                            <button
                                                type="button"
                                                class="cc-rail-tab"
                                                x-bind:class="{ 'is-active': groupTab === 'members' }"
                                                x-on:click="groupTab = 'members'"
                                                style="cursor: pointer;"
                                            >
                                                <i class="fa-solid fa-users" style="font-size: 11px;"></i>
                                                
                                            </button>

                                            @can('manageMembers', $selectedConversation)
                                                @if($selectedConversation->type !== 'direct_message')
                                                    <button
                                                        type="button"
                                                        class="cc-rail-tab"
                                                        x-bind:class="{ 'is-active': groupTab === 'add' }"
                                                        x-on:click="groupTab = 'add'"
                                                        style="cursor: pointer;"
                                                    >
                                                        <i class="fa-solid fa-user-plus" style="font-size: 11px;"></i>
                                                
                                                        
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="cc-rail-tab"
                                                        x-bind:class="{ 'is-active': groupTab === 'edit' }"
                                                        x-on:click="groupTab = 'edit'"
                                                        style="cursor: pointer;"
                                                    >
                                                        <i class="fa-solid fa-pen-to-square" style="font-size: 11px;"></i>
                                                    
                                                    </button>
                                                @endif
                                            @endcan
                                        </nav>

                                        <div x-show="groupTab === 'members'" style="display: flex; flex-direction: column; gap: 10px;">
                                            <div class="b360-chat-member-picker" style="max-height: 320px; overflow-y: auto; border: 1px solid #E2E8F2; border-radius: 12px; background: #fff;">
                                                @foreach($selectedConversation->activeMembers as $member)
                                                    @if($member->user)
                                                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #F1F5F9; gap: 12px;">
                                                            <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                                                                <x-ui.user-avatar :user="$member->user" :label="$member->user->name" style="width: 34px; height: 34px; font-size: 12px; flex-shrink: 0;" />
                                                                <div style="min-width: 0; flex: 1;">
                                                                    <strong style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                                        {{ $member->user->name }}
                                                                        @if((int)$selectedConversation->owner_user_id === (int)$member->user_id)
                                                                            <span style="font-size: 10px; font-weight: 700; background: #EEF4FF; color: #F6740C; padding: 2px 7px; border-radius: 10px; flex-shrink: 0;">Owner</span>
                                                                        @endif
                                                                        @if((int)$member->user_id === (int)auth()->id())
                                                                            <span style="font-size: 10px; font-weight: 700; background: #F1F5F9; color: #64748B; padding: 2px 7px; border-radius: 10px; flex-shrink: 0;">You</span>
                                                                        @endif
                                                                    </strong>
                                                                    <small style="display: block; color: #64748B; font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $member->user->role?->name ?? $member->user->email }}</small>
                                                                </div>
                                                            </div>
                                                            @if($selectedConversation->type !== 'direct_message')
                                                                @if((int)$member->user_id === (int)auth()->id() && (int)$selectedConversation->owner_user_id !== (int)auth()->id())
                                                                
                                                                @elseif((int)$selectedConversation->owner_user_id !== (int)$member->user_id && auth()->user()->can('manageMembers', $selectedConversation))
                                                                
                                                                @endif
                                                            @endif
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>

                                        @can('manageMembers', $selectedConversation)
                                            @if($selectedConversation->type !== 'direct_message')
                                                <div x-show="groupTab === 'add'" x-cloak x-data="peopleFilter" style="display: flex; flex-direction: column; gap: 12px;">
                                                    <form method="POST" action="{{ route('collaboration.chat.conversations.members.store', $selectedConversation) }}" style="display: flex; flex-direction: column; gap: 12px;">
                                                        @csrf
                                                        @php
                                                            $existingMemberIds = $selectedConversation->activeMembers->pluck('user_id')->all();
                                                            $availableAddUsers = $users->reject(fn($u) => in_array($u->id, $existingMemberIds, true));
                                                        @endphp
                                                        <div class="b360-chat-member-picker" style="max-height: 240px; overflow-y: auto; border: 1px solid #E2E8F2; border-radius: 12px; background: #fff;">
                                                            @foreach($availableAddUsers as $user)
                                                                <label class="b360-chat-member-option" data-people-option data-search="{{ str($user->name.' '.$user->email.' '.($user->role?->name ?? ''))->lower() }}">
                                                                    <input type="checkbox" name="member_user_ids[]" value="{{ $user->id }}" x-on:change="togglePerson">
                                                                    <span>
                                                                        {{ $user->name }}
                                                                        <small>{{ $user->role?->name ?? $user->email }}</small>
                                                                    </span>
                                                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                                                </label>
                                                            @endforeach
                                                            @if($availableAddUsers->isEmpty())
                                                                <p style="padding: 20px; text-align: center; color: #64748B; font-size: 13px; margin: 0;">All internal employees are already members.</p>
                                                            @endif
                                                            <p class="b360-chat-member-empty" x-show="noPeopleMatches" x-cloak style="padding: 16px; text-align: center; color: #64748B; font-size: 13px; margin: 0;">No matching employees.</p>
                                                        </div>
                                                        <div style="display: flex; justify-content: flex-end; padding-top: 4px;">
                                                            <button type="submit" class="b360-primary-btn" x-bind:disabled="selectedPeopleCount < 1">
                                                                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>

                                                <div x-show="groupTab === 'edit'" x-cloak style="display: flex; flex-direction: column; gap: 12px;">
                                                    <form method="POST" action="{{ route('collaboration.chat.conversations.update', $selectedConversation) }}" class="b360-chat-form" style="gap: 12px;">
                                                        @csrf @method('PATCH')
                                                        <label style="display: block;">
                                                            <span>Group / Channel title *</span>
                                                            <input type="text" name="title" maxlength="160" required value="{{ old('title', $selectedConversation->title) }}">
                                                        </label>
                                                        <label style="display: block;">
                                                            <span>Description / Purpose <small>(optional)</small></span>
                                                            <textarea name="description" maxlength="500" placeholder="Purpose of this group or channel" style="min-height: 80px;">{{ old('description', $selectedConversation->description) }}</textarea>
                                                        </label>
                                                        <div style="display: flex; justify-content: flex-end; padding-top: 4px;">
                                                            <button type="submit" class="b360-primary-btn" style="width: 85px;font-size: 15px;text-align: center;">
                                                            Save
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        @endcan
                                    </div>
                                </div>
                            </details>
                            <form method="POST" action="{{ route('collaboration.chat.conversations.read', $selectedConversation) }}">@csrf @method('PATCH')<button type="submit" title="Mark conversation read" aria-label="Mark conversation read"><i class="fa-solid fa-check"></i></button></form>
                            @can('archive', $selectedConversation)
                                <form method="POST" action="{{ route('collaboration.chat.conversations.archive', $selectedConversation) }}">@csrf @method('PATCH')<button type="submit" title="Archive conversation" aria-label="Archive conversation"><i class="fa-solid fa-box-archive"></i></button>
                            </form>
                            @endcan
                        @endif
                    </div>
                </header>

                @include('collaboration.chat.partials.timeline', [
                    'selectedConversation' => $selectedConversation,
                    'chatMessages' => $chatMessages,
                ])

                @if ($canPost)
                    <footer class="b360-thread-composer">
                        <div class="b360-composer-stack">
                            <form method="POST" action="{{ route('collaboration.chat.conversations.messages.store', $selectedConversation) }}" enctype="multipart/form-data" class="b360-composer-box" x-ref="composer" x-on:submit.prevent="sendMessage" onpaste="window.handlePaste ? window.handlePaste(event) : null">
                                @csrf
                                <textarea name="body" maxlength="10000" placeholder="Write a message…" aria-label="Message" x-on:input="handleComposerInput" x-on:keydown.enter="handleComposerKeydown" onpaste="window.handlePaste ? window.handlePaste(event) : null" x-bind:disabled="busy"></textarea>
                                <div class="b360-chat-attachment-selection" x-show="hasSelectedAttachments" x-cloak aria-label="Selected attachments">
                                    <template x-for="attachment in selectedAttachments" x-bind:key="attachment.key">
                                        <span class="b360-chat-selected-file">
                                            <i class="fa-solid fa-file" aria-hidden="true"></i>
                                            <span x-text="attachment.name"></span>
                                            <button type="button" x-bind:data-file-key="attachment.key" x-on:click="removeAttachment" aria-label="Remove attachment"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                                        </span>
                                    </template>
                                </div>
                                <div class="b360-composer-tools">
                                    @if ($chatOptions['can_upload'] ?? false)
                                        <label class="b360-composer-tool" title="Attach files" aria-label="Attach files"><i class="fa-solid fa-paperclip"></i><input type="file" name="attachments[]" multiple x-on:change="selectAttachments"></label>
                                    @endif
                                    <select name="parent_message_id" hidden tabindex="-1" aria-hidden="true">
                                        <option value="">New message</option>
                                        @foreach ($chatMessages->take(-20) as $parentMessage)
                                            <option value="{{ $parentMessage->id }}">{{ $parentMessage->message_number }} · {{ str($parentMessage->body)->squish()->limit(50) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="priority" value="normal">
                                    <details class="b360-composer-mentions" name="composer-panels" x-ref="mentionMenu">
                                        <summary class="b360-composer-tool" title="Mention a member" aria-label="Mention a member"><i class="fa-solid fa-at"></i></summary>
                                        <div class="b360-mention-picker">
                                            <header><div><strong>Mention a member</strong><small>Only members of this conversation are available.</small></div><button type="button" x-on:click="closeComposerPanel" aria-label="Close member mentions"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                                            <label><span>Find conversation members</span><input type="search" x-ref="mentionSearch" placeholder="Search name, email or role" x-on:input="filterMentionOptions"></label>
                                            <div class="b360-mention-options">
                                                @foreach ($selectedConversation->activeMembers as $member)
                                                    @if ($member->user && (int) $member->user_id !== (int) auth()->id())
                                                        <label data-mention-option data-search="{{ str($member->user->name.' '.$member->user->email.' '.($member->user->role?->name ?? ''))->lower() }}">
                                                            <input type="checkbox" name="metadata[mentions][]" value="{{ $member->user_id }}" data-mention-name="{{ $member->user->name }}" x-on:change="toggleMention">
                                                            <x-ui.user-avatar :user="$member->user" :label="$member->user->name" class="b360-mention-avatar" />
                                                            <span><strong>{{ $member->user->name }}</strong><small>{{ $member->user->role?->name ?? $member->user->email }}</small></span>
                                                        </label>
                                                    @endif
                                                @endforeach
                                                <p x-show="noMentionMatches" x-cloak>No matching members.</p>
                                            </div>
                                        </div>
                                    </details>
                                    <span class="b360-composer-hint">Enter to send · Shift+Enter for newline</span>
                                    <button class="b360-composer-send" type="submit" aria-label="Send message" x-bind:disabled="busy"><i class="fa-solid fa-paper-plane"></i></button>
                                </div>
                            </form>
                            @if ($chatOptions['can_create_poll'] ?? false)
                                <details class="b360-chat-poll-create" name="composer-panels" x-data="pollComposer" @if ($errors->hasAny(['question', 'options', 'options.*', 'allows_multiple', 'closes_at'])) open @endif>
                                    <summary title="Create poll" aria-label="Create poll"><i class="fa-solid fa-chart-simple"></i></summary>
                                    <form method="POST" action="{{ route('collaboration.chat.conversations.polls.store', $selectedConversation) }}" class="b360-chat-form b360-poll-dialog" role="dialog" aria-modal="true" aria-labelledby="create-poll-title">
                                        @csrf
                                        <header class="b360-poll-dialog-head"><div><h2 id="create-poll-title">Create poll</h2><p>Ask conversation members to vote.</p></div><button type="button" x-on:click="closePollPanel" aria-label="Close create poll"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
                                        <div class="b360-poll-dialog-body">
                                            @if ($errors->hasAny(['question', 'options', 'options.*', 'allows_multiple', 'closes_at']))
                                                <div class="b360-chat-create-errors" role="alert"><strong>Please review the poll details.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                                            @endif
                                            <label><span>Question</span><input name="question" maxlength="255" value="{{ old('question') }}" placeholder="What should the team decide?" required></label>
                                            <section class="b360-poll-option-builder" aria-labelledby="poll-options-label">
                                                <div class="b360-poll-option-head"><div><strong id="poll-options-label">Answer options</strong><small x-text="pollOptionCountLabel"></small></div><button type="button" x-on:click="addPollOption" x-bind:disabled="!canAddPollOption"><i class="fa-solid fa-plus" aria-hidden="true"></i> Add option</button></div>
                                                <div class="b360-poll-option-list">
                                                    <template x-for="(option, index) in pollOptions" x-bind:key="index">
                                                        <label class="b360-poll-option-row"><span>Option <b x-text="index + 1"></b></span><div><input name="options[]" maxlength="120" x-bind:value="option" x-bind:data-option-index="index" x-on:input="updatePollOption" required><button type="button" x-bind:data-option-index="index" x-on:click="removePollOption" x-show="pollOptions.length > 2" aria-label="Remove poll option"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div></label>
                                                    </template>
                                                </div>
                                            </section>
                                            <div class="b360-poll-settings">
                                                <label class="b360-check-row"><input type="checkbox" name="allows_multiple" value="1" @checked(old('allows_multiple'))><span>Allow multiple selections</span></label>
                                                <label><span>Close date <small>(optional)</small></span><input type="datetime-local" name="closes_at" value="{{ old('closes_at') }}"></label>
                                            </div>
                                        </div>
                                        <footer class="b360-poll-dialog-actions"><button type="button" class="b360-secondary-btn" x-on:click="closePollPanel">Cancel</button><button class="b360-primary-btn" type="submit">Create poll</button></footer>
                                    </form>
                                    <script type="application/json" x-ref="pollSeed">@json(old('options', ['', '']))</script>
                                </details>
                            @endif
                        </div>
                        <p class="b360-chat-composer-status" x-show="statusMessage" x-text="statusMessage" x-bind:class="statusClass" x-cloak></p>
                    </footer>
                @else
                    <div class="b360-chat-readonly">This conversation is read-only for your current role.</div>
                @endif
            @else
                <div class="b360-collab-empty b360-collab-empty-large"><span class="b360-empty-icon">◯</span><strong>No conversation selected</strong><span>Choose a conversation from the left panel or start a new internal conversation.</span></div>
            @endif
        </section>
    </section>
@endsection
