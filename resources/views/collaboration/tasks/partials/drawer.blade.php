@php
    $drawerClose = route('collaboration.tasks.index', $taskQuery(['task_id'=>null]));
    $dependencyIds = collect(data_get($selectedTask->metadata,'dependency_task_ids',[]))->map(fn($id)=>(int)$id);
    $watcherIds = collect(data_get($selectedTask->metadata,'watcher_user_ids',[]))->map(fn($id)=>(int)$id);
    $activeTab = $errors->hasAny(['body']) ? 'comments' : 'details';
    $checklist = collect($selectedTask->checklist ?? []);
    $progressParts = $selectedTask->subtasks->count() + $checklist->count();
    $progressDone = $selectedTask->subtasks->where('status','completed')->count() + $checklist->where('done',true)->count();
    $progress = $progressParts ? (int) round($progressDone / $progressParts * 100) : ($selectedTask->status === 'completed' ? 100 : 0);
    $completionApproval = $selectedTask->completionApprovals->firstWhere('status', 'pending');
    $allowedTaskTransitions = $taskTransitionTargets[$selectedTask->id] ?? [];
    $transferRequiresApproval = (bool) data_get($taskSetting?->value, 'transfer_requires_approval', true);
    $pendingTransfer = $selectedTask->transferRequests->firstWhere('status', 'pending');
@endphp
<style>
    .tm-transfer-behaviour>i  { height: 15px !important; }
    </style>
<template x-teleport="body">
<div class="tm-drawer-scrim" x-data="taskDrawer" data-initial-tab="{{ $activeTab }}" data-open-transfer="{{ old('form_context') === 'transfer' ? '1' : '0' }}" data-open-assignee="{{ old('form_context') === 'assign' ? '1' : '0' }}" x-on:keydown.escape.window="escape">
<aside class="tm-drawer" x-ref="drawer" x-bind:data-layout="compactInfo ? 'compact' : 'wide'" role="dialog" aria-modal="true" aria-labelledby="task-drawer-title">
    <header class="tm-dr-head">
        <div class="tm-dr-crumb"><span class="tm-dr-id">{{ $selectedTask->task_number }}</span>@if($selectedTask->module_context)<span class="blade-status-pill">{{ str_replace('_',' ',$selectedTask->module_context) }}</span>@endif<div class="tm-dr-actions">
            @can('requestTransfer',$selectedTask)<button class="tm-iconbtn" type="button" x-on:click="openTransfer" aria-label="Transfer task"><i class="fa-solid fa-right-left"></i></button>@endcan
            <button class="tm-iconbtn" type="button" x-on:click="copyLink" aria-label="Copy task link"><i class="fa-solid fa-link"></i></button>
            <div class="tm-action-menu-wrap"><button class="tm-iconbtn" type="button" x-on:click="toggleActionMenu" x-bind:aria-expanded="actionMenuOpen.toString()" aria-label="More task actions"><i class="fa-solid fa-ellipsis"></i></button><div class="tm-action-menu" x-show="actionMenuOpen" x-cloak x-on:click.outside="actionMenuOpen=false">
                @can('updateStatus',$selectedTask)
                    @if($selectedTask->status!=='completed')
                        <form method="POST" action="{{ route('collaboration.tasks.status.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}"><input type="hidden" name="status" value="completed"><input type="hidden" name="note" value="Task marked complete"><button type="submit"><i class="fa-solid fa-check"></i> Mark complete</button></form>
                    @endif
                @endcan
                <form method="POST" action="{{ route('collaboration.tasks.duplicate',$selectedTask) }}">@csrf<input type="hidden" name="client_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button type="submit"><i class="fa-regular fa-clone"></i> Duplicate</button></form>
                @can('archive',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.archive',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="reason" value="Archived from task actions"><button type="submit"><i class="fa-solid fa-box-archive"></i> Archive</button></form>@endcan
                @can('updateStatus',$selectedTask)<form class="is-danger" method="POST" action="{{ route('collaboration.tasks.status.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}"><input type="hidden" name="status" value="cancelled"><input type="hidden" name="note" value="Task cancelled"><button type="submit"><i class="fa-solid fa-xmark"></i> Cancel task</button></form>@endcan
            </div></div>
            <a class="tm-iconbtn" href="{{ $drawerClose }}" aria-label="Close task"><i class="fa-solid fa-xmark"></i></a></div></div>
        <h2 class="tm-dr-title" id="task-drawer-title">{{ $selectedTask->title }}</h2>
        <div class="tm-dr-statusbar">
            @can('updateStatus',$selectedTask)
                <details class="tm-control-menu"><summary class="tm-statbtn" aria-label="Change task status"><span class="blade-status-pill">{{ $statuses[$selectedTask->status] ?? $selectedTask->status }}</span><i class="fa-solid fa-chevron-down"></i></summary><div class="tm-control-menu-list" role="menu">@foreach($allowedTaskTransitions as $value)<form method="POST" action="{{ route('collaboration.tasks.status.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}"><input type="hidden" name="status" value="{{ $value }}"><input type="hidden" name="note" value="Status updated from task detail"><button type="submit" role="menuitem"><span class="blade-status-pill">{{ $statuses[$value] ?? str($value)->replace('_',' ')->title() }}</span></button></form>@endforeach</div></details>
            @else<span class="blade-status-pill">{{ $statuses[$selectedTask->status] ?? $selectedTask->status }}</span>@endcan
            @can('updateDetails',$selectedTask)
                <details class="tm-control-menu"><summary class="tm-statbtn" aria-label="Change task priority"><span class="tm-pri tm-pri-{{ $selectedTask->priority }}"><span class="pdot"></span>{{ $priorities[$selectedTask->priority] ?? $selectedTask->priority }}</span><i class="fa-solid fa-chevron-down"></i></summary><div class="tm-control-menu-list" role="menu">@foreach($priorities as $value=>$label)<form method="POST" action="{{ route('collaboration.tasks.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}"><input type="hidden" name="priority" value="{{ $value }}"><input type="hidden" name="note" value="Priority updated from task detail"><button type="submit" role="menuitem"><span class="tm-pri tm-pri-{{ $value }}"><span class="pdot"></span>{{ $label }}</span>@if($selectedTask->priority===$value)<i class="fa-solid fa-check"></i>@endif</button></form>@endforeach</div></details>
            @else<span class="tm-pri tm-pri-{{ $selectedTask->priority }}"><span class="pdot"></span>{{ $priorities[$selectedTask->priority] ?? $selectedTask->priority }}</span>@endcan
            <span class="tm-due-inline"><i class="fa-regular fa-calendar"></i>{{ $selectedTask->due_at?->isToday() ? 'Today' : ($selectedTask->due_at?->format('d M') ?? 'No due date') }}</span>
            <span class="tm-progress-inline"><i style="--progress:{{ $progress }}%"></i>{{ $progress }}%</span>
        </div>
    </header>
    <div class="tm-dr-tabs" role="tablist" aria-label="Task details" x-on:keydown="navigateTabs">
        @foreach(['details'=>'Details','subtasks'=>'Subtasks','checklist'=>'Checklist','comments'=>'Comments','activity'=>'Activity','time'=>'Time'] as $key=>$label)<button type="button" id="task-tab-{{ $key }}" class="tm-dr-tab" x-bind:class="activeTab === '{{ $key }}' ? 'on' : ''" x-bind:aria-selected="(activeTab === '{{ $key }}').toString()" aria-controls="task-panel-{{ $key }}" data-tab-key="{{ $key }}" x-on:click="selectTab" role="tab" tabindex="-1">{{ $label }} @if($key==='subtasks')<span class="cnt">{{ $selectedTask->subtasks->count() }}</span>@elseif($key==='checklist')<span class="cnt">{{ count($selectedTask->checklist ?? []) }}</span>@elseif($key==='comments')<span class="cnt">{{ $selectedTask->comments->count() }}</span>@endif</button>@endforeach
        <button type="button" id="task-tab-info" class="tm-dr-tab" x-show="compactInfo" x-cloak x-bind:class="activeTab === 'info' ? 'on' : ''" x-bind:aria-selected="(activeTab === 'info').toString()" aria-controls="task-panel-info" data-tab-key="info" x-on:click="selectTab" role="tab" tabindex="-1"><i class="fa-solid fa-circle-info"></i> Task Info</button>
    </div>
    <div class="tm-dr-body">
        <main class="tm-dr-main" x-show="!compactInfo || activeTab !== 'info'">

            <section id="task-panel-details" data-task-panel x-show="activeTab === 'details'" x-cloak class="tm-dr-panel tm-detail-stack" role="tabpanel" aria-labelledby="task-tab-details" tabindex="0">
                <article class="tm-detail-card"><header class="tm-detail-card-head"><i class="fa-regular fa-file-lines"></i><h3>Description</h3></header><div class="tm-detail-card-body">@if($selectedTask->description)<p class="tm-desc">{{ $selectedTask->description }}</p>@else<p class="tm-empty-copy">No description added.</p>@endif</div></article>
                <article class="tm-detail-card"><header class="tm-detail-card-head"><i class="fa-solid fa-tags"></i><h3>Tags and context</h3></header><div class="tm-detail-card-body tm-card-tags">@if($selectedTask->module_context)<span class="tm-tag">{{ str_replace('_',' ',$selectedTask->module_context) }}</span>@endif @if($selectedTask->project)<span class="tm-tag">{{ $selectedTask->project->code }}</span>@endif @if(!$selectedTask->module_context && !$selectedTask->project)<p class="tm-empty-copy">No tags added.</p>@endif</div></article>
                <article class="tm-detail-card"><header class="tm-detail-card-head"><i class="fa-solid fa-paperclip"></i><h3>Attachments</h3><span class="cnt">{{ $selectedTask->attachments->count() }}</span></header><div class="tm-detail-card-body">
                    @forelse($selectedTask->attachments as $attachment)<div class="tm-attachment-row"><span class="tm-attachment-icon">@if(str_starts_with($attachment->mime_type,'image/'))<i class="fa-regular fa-file-image"></i>@elseif(str_starts_with($attachment->mime_type,'video/'))<i class="fa-regular fa-file-video"></i>@elseif(str_starts_with($attachment->mime_type,'audio/'))<i class="fa-regular fa-file-audio"></i>@elseif($attachment->mime_type==='application/pdf')<i class="fa-regular fa-file-pdf"></i>@else<i class="fa-regular fa-file"></i>@endif</span><span><b>{{ $attachment->original_filename }}</b><small>{{ number_format($attachment->size_bytes / 1024, 1) }} KB · {{ str_replace('_',' ',$attachment->scan_status) }}</small></span>@if((str_starts_with($attachment->mime_type,'image/') || str_starts_with($attachment->mime_type,'video/') || str_starts_with($attachment->mime_type,'audio/') || $attachment->mime_type==='application/pdf') && !in_array($attachment->scan_status,['blocked','failed'],true))<a class="tm-iconbtn" target="_blank" rel="noopener" href="{{ route('collaboration.tasks.attachments.preview',[$selectedTask,$attachment]) }}" aria-label="Preview {{ $attachment->original_filename }}"><i class="fa-solid fa-eye"></i></a>@endif<a class="tm-iconbtn" href="{{ route('collaboration.tasks.attachments.download',[$selectedTask,$attachment]) }}" aria-label="Download {{ $attachment->original_filename }}"><i class="fa-solid fa-download"></i></a>@can('updateDetails',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.attachments.destroy',[$selectedTask,$attachment]) }}">@csrf @method('DELETE')<button class="tm-iconbtn is-danger" type="submit" aria-label="Remove {{ $attachment->original_filename }}"><i class="fa-solid fa-xmark"></i></button></form>@endcan</div>@empty<div class="tm-empty-panel"><span class="tm-empty-ic"><i class="fa-solid fa-paperclip"></i></span><span><b>No attachments</b><small>Files added to this task will remain private to authorized task users.</small></span></div>@endforelse
                    @can('updateDetails',$selectedTask)
                        <form class="tm-attachment-upload" method="POST" enctype="multipart/form-data" action="{{ route('collaboration.tasks.attachments.store',$selectedTask) }}">
                            @csrf
                            <label class="tm-file-drop">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <span id="tm-file-label-text">Choose a file (Images, PDF, Office, ZIP up to 5MB)</span>
                                <input type="file" name="attachment" id="tm-file-input" required accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip" onchange="validateTaskAttachmentInput(this)">
                            </label>
                            <button class="blade-primary-action" type="submit">Upload</button>
                        </form>
                        <div id="tm-file-js-error" style="display:none; color:#EF4444; font-size:12px; margin-top:8px; font-weight:600;"></div>
                        @error('attachment')
                            <div style="color:#EF4444; font-size:12px; margin-top:8px; font-weight:600;">
                                <i class="fa-solid fa-circle-exclamation"></i> {{ $message }}
                            </div>
                        @enderror
                    @endcan
                </div></article>
                <article class="tm-detail-card"><header class="tm-detail-card-head"><i class="fa-solid fa-link"></i><h3>Dependencies</h3></header><div class="tm-detail-card-body">
                    @if($dependencyIds->isEmpty())<div class="tm-empty-panel"><span class="tm-empty-ic"><i class="fa-solid fa-link"></i></span><span><b>No dependencies added</b><small>Link another task when work must be completed first.</small></span></div>@else<div class="tm-dependency-list">@foreach($dependencyIds as $dependencyId)<div class="tm-sub-row"><i class="fa-solid fa-link"></i><span class="tm-sub-title">{{ optional($tasks->getCollection()->firstWhere('id',$dependencyId))->task_number ?? 'Task #'.$dependencyId }}</span></div>@endforeach</div>@endif
                    @can('updateDetails',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.dependencies.update',$selectedTask) }}" class="tm-dep-add">@csrf @method('PATCH')@foreach($dependencyIds as $dependencyId)<input type="hidden" name="dependency_task_ids[]" value="{{ $dependencyId }}">@endforeach<select class="tm-select" name="dependency_task_ids[]" aria-label="Add dependency"><option value="">Select dependency task</option>@foreach($tasks->getCollection()->where('id','!=',$selectedTask->id)->whereNotIn('id',$dependencyIds) as $dependency)<option value="{{ $dependency->id }}">{{ $dependency->task_number }} · {{ $dependency->title }}</option>@endforeach</select><button class="blade-primary-action" type="submit">Add dependency</button></form>@endcan
                </div></article>
                @if($selectedTask->recurrenceRule)
                <article class="tm-detail-card"><header class="tm-detail-card-head"><i class="fa-solid fa-rotate"></i><h3>Recurrence</h3><span class="cnt">{{ ucfirst($selectedTask->recurrenceRule->status) }}</span></header><div class="tm-detail-card-body"><div class="tm-empty-panel"><span class="tm-empty-ic"><i class="fa-regular fa-calendar"></i></span><span><b>{{ ucfirst($selectedTask->recurrenceRule->frequency) }} · every {{ $selectedTask->recurrenceRule->interval }}</b><small>Next task: {{ $selectedTask->recurrenceRule->next_run_at?->format('d M Y, h:i A') ?? 'No future occurrence' }}</small></span></div>
                    @can('updateDetails',$selectedTask)
                        @if($selectedTask->recurrenceRule->status==='active')
                        <div class="tm-inline-actions"><form method="POST" action="{{ route('collaboration.tasks.recurrence.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="skip"><button class="blade-secondary-action" type="submit">Skip next</button></form><form method="POST" action="{{ route('collaboration.tasks.recurrence.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="cancel"><button class="blade-secondary-action is-danger" type="submit">Cancel future</button></form></div>
                        @endif
                    @endcan
                </div></article>
                @endif
            </section>

            <section id="task-panel-subtasks" data-task-panel x-show="activeTab === 'subtasks'" x-cloak class="tm-dr-panel" role="tabpanel" aria-labelledby="task-tab-subtasks" tabindex="0">
                @forelse($selectedTask->subtasks as $subtask)<div class="tm-sub-row"><span class="tm-sub-check {{ $subtask->status==='completed' ? 'on' : '' }}"><i class="fa-solid fa-check"></i></span><span class="tm-sub-title {{ $subtask->status==='completed' ? 'done' : '' }}">{{ $subtask->title }}</span><span class="blade-status-pill">{{ str_replace('_',' ',$subtask->status) }}</span>@can('manageSubtasks',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.subtasks.update',[$selectedTask,$subtask]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $subtask->status==='completed' ? 'open' : 'completed' }}"><button class="tm-iconbtn" aria-label="Toggle subtask"><i class="fa-solid fa-check"></i></button></form>@endcan</div>@empty<p class="tm-empty-copy">No subtasks have been added.</p>@endforelse
                @can('manageSubtasks',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.subtasks.store',$selectedTask) }}" class="tm-addline-form">@csrf<input class="tm-input" name="title" maxlength="255" required placeholder="Add a subtask"><button class="blade-primary-action" type="submit">Add</button></form>@endcan
            </section>

            <section id="task-panel-checklist" data-task-panel x-show="activeTab === 'checklist'" x-cloak class="tm-dr-panel" role="tabpanel" aria-labelledby="task-tab-checklist" tabindex="0">
                <div class="tm-check-progress"><b>{{ $checklist->where('done',true)->count() }} of {{ $checklist->count() }}</b><span><i style="width:{{ $checklist->count() ? $checklist->where('done',true)->count()/$checklist->count()*100 : 0 }}%"></i></span></div>
                @can('updateChecklist',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.checklist.update',$selectedTask) }}">@csrf @method('PATCH')@forelse(($selectedTask->checklist ?? []) as $index=>$item)<label class="tm-check-row"><input type="hidden" name="checklist[{{ $index }}][done]" value="0"><input type="checkbox" name="checklist[{{ $index }}][done]" value="1" @checked($item['done'] ?? false)><input type="text" name="checklist[{{ $index }}][label]" value="{{ $item['label'] ?? $item['text'] ?? '' }}" maxlength="255" required></label>@empty<p class="tm-empty-copy">No checklist items have been added.</p>@endforelse<div class="tm-addline-form"><input class="tm-input" name="new_item" maxlength="255" placeholder="Add checklist item..."><button class="blade-primary-action" type="submit">Save & add</button></div></form>@else<p class="tm-empty-copy">Checklist updates are not available for this role.</p>@endcan
            </section>

            <section id="task-panel-comments" data-task-panel x-show="activeTab === 'comments'" x-cloak class="tm-dr-panel" role="tabpanel" aria-labelledby="task-tab-comments" tabindex="0">
                @forelse($selectedTask->comments as $comment)<article class="tm-comment"><span class="tm-card-owner">{{ strtoupper(substr($comment->author?->name ?? 'U',0,1)) }}</span><div><b>{{ $comment->author?->name ?? 'User' }}</b><time>{{ $comment->created_at?->diffForHumans() }}</time><p>{{ $comment->body }}</p></div></article>@empty<p class="tm-empty-copy">No comments yet.</p>@endforelse
                @can('comment',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.comments.store',$selectedTask) }}" class="tm-comment-form tm-inline-mention-composer" x-data="taskMentionComposer" x-on:click.outside="close">@csrf<div class="tm-comment-input-wrap"><textarea x-ref="body" x-on:input="input" class="tm-textarea" name="body" maxlength="2000" required placeholder="Write a comment... Type @ to mention a teammate"></textarea><button class="tm-iconbtn" type="button" x-on:click="show" aria-label="Mention a teammate"><i class="fa-solid fa-at"></i></button><div class="tm-mention-popover" x-show="open" x-cloak><header><b>Mention a teammate</b><small>Continue typing to filter</small></header><div class="tm-mention-list">@foreach($users as $userOption)<button type="button" data-task-mention-option data-person-id="{{ $userOption->id }}" data-person-name="{{ $userOption->name }}" data-person-search="{{ strtolower($userOption->name.' '.$userOption->email.' '.($userOption->role?->name ?? '').' '.($userOption->employee?->department ?? '')) }}" x-on:click="select"><span class="tm-card-owner">{{ strtoupper(substr($userOption->name,0,1)) }}</span><span><b>{{ $userOption->name }}</b><small>{{ $userOption->role?->name }} · {{ $userOption->employee?->department ?? $userOption->email }}</small></span></button><input type="checkbox" hidden data-mention-id="{{ $userOption->id }}" name="mentions[]" value="{{ $userOption->id }}">@endforeach</div></div></div><button class="blade-primary-action" type="submit"><i class="fa-solid fa-paper-plane"></i> Comment</button></form>@endcan
            </section>

            <section id="task-panel-activity" data-task-panel x-show="activeTab === 'activity'" x-cloak class="tm-dr-panel" role="tabpanel" aria-labelledby="task-tab-activity" tabindex="0">@forelse(($selectedTask->workflow_history ?? []) as $row)<div class="tm-act-row"><span class="tm-act-ic"><i class="fa-solid fa-clock-rotate-left"></i></span><span><b>{{ str_replace('_',' ',$row['status'] ?? 'updated') }}</b><small>{{ $row['note'] ?? 'Task updated' }}</small></span><time>{{ isset($row['at']) ? \Illuminate\Support\Carbon::parse($row['at'])->diffForHumans() : '' }}</time></div>@empty<p class="tm-empty-copy">No activity recorded.</p>@endforelse</section>

            <section id="task-panel-time" data-task-panel x-show="activeTab === 'time'" x-cloak class="tm-dr-panel" role="tabpanel" aria-labelledby="task-tab-time" tabindex="0">
                @forelse($selectedTask->timeLogs as $log)<div class="tm-time-row"><span><b>{{ $log->minutes }} min</b><small>{{ $log->note ?: 'Time logged' }}</small></span><time>{{ $log->logged_on?->format('d M Y') }}</time></div>@empty<p class="tm-empty-copy">No time has been logged.</p>@endforelse
                @can('logTime',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.time-logs.store',$selectedTask) }}" class="tm-addline-form">@csrf<input class="tm-input" type="number" name="minutes" min="1" max="1440" required placeholder="Minutes"><input class="tm-input" name="note" maxlength="1000" placeholder="Work completed"><button class="blade-primary-action" type="submit">Log time</button></form>@endcan
            </section>
        </main>
        <aside id="task-panel-info" class="tm-dr-side" x-show="!compactInfo || activeTab === 'info'" x-bind:class="{ 'is-tab-panel': compactInfo }" x-bind:role="compactInfo ? 'tabpanel' : null" x-bind:aria-labelledby="compactInfo ? 'task-tab-info' : null" tabindex="0">
            @if($completionApproval)
                <div class="tm-meta-block tm-approval-decision">
                    <span>Completion approval</span>
                    <b>Requested by {{ $completionApproval->requestedBy?->name ?? 'Task owner' }}</b>
                    <small>{{ $completionApproval->request_note }}</small>
                    @can('approveCompletion', $selectedTask)
                        <form method="POST" action="{{ route('collaboration.tasks.completion-approvals.decide', $completionApproval) }}">@csrf @method('PATCH')
                            <textarea class="tm-textarea" name="note" maxlength="1000" required placeholder="Decision note"></textarea>
                            <div class="tm-decision-actions"><button class="blade-primary-action" name="decision" value="approve" type="submit"><i class="fa-solid fa-check"></i> Approve</button><button class="blade-secondary-action is-danger" name="decision" value="reject" type="submit"><i class="fa-solid fa-xmark"></i> Reject</button></div>
                        </form>
                    @endcan
                </div>
            @elseif($selectedTask->status === 'rejected')
                @can('reopen', $selectedTask)<div class="tm-meta-block tm-approval-decision"><span>Rejected task</span><form method="POST" action="{{ route('collaboration.tasks.reopen', $selectedTask) }}">@csrf @method('PATCH')<textarea class="tm-textarea" name="note" maxlength="1000" required placeholder="Reason for reopening"></textarea><button class="blade-primary-action" type="submit">Reopen task</button></form></div>@endcan
            @endif
            <div class="tm-meta-block"><span>Owner (assigned by)</span><b class="tm-person-line"><span class="tm-card-owner">{{ strtoupper(substr($selectedTask->createdBy?->name ?? 'U',0,1)) }}</span>{{ $selectedTask->createdBy?->name ?? 'Unknown' }}</b></div>
            <div class="tm-meta-block tm-assignee-block">
                <span>Assignees</span>
                @php
                    $allAssignees = $selectedTask->assignees->isNotEmpty() ? $selectedTask->assignees : collect(array_filter([$selectedTask->assignedTo]));
                @endphp
                @forelse($allAssignees as $assigneeUser)
                    <b class="tm-person-line" style="margin-bottom:3px;"><span class="tm-card-owner">{{ strtoupper(substr($assigneeUser->name ?? 'U',0,1)) }}</span>{{ $assigneeUser->name }}</b>
                @empty
                    <b class="tm-person-line"><span class="tm-card-owner">U</span>Unassigned</b>
                @endforelse
                @if($pendingTransfer)<small class="tm-transfer-pending">Transfer pending approval</small>@elseif($selectedTask->assigned_to_user_id)@can('requestTransfer',$selectedTask)<button class="tm-assignee-add" type="button" x-on:click="openTransfer" aria-label="Transfer to another assignee"><i class="fa-solid fa-right-left"></i></button>@endcan @else @can('assign',$selectedTask)<button class="tm-assignee-add" type="button" x-on:click="toggleAssignee" x-bind:aria-expanded="assigneeOpen.toString()" aria-label="Assign task"><i class="fa-solid fa-plus"></i></button>@endcan @endif
            </div>
            <div class="tm-meta-block"><span>Timeline</span><div class="tm-timeline-meta"><small>Start</small><b>{{ data_get($selectedTask->metadata,'planned_start_at') ? \Illuminate\Support\Carbon::parse(data_get($selectedTask->metadata,'planned_start_at'))->format('d M Y') : $selectedTask->started_at?->format('d M Y') ?? 'Not started' }}</b><small>Due</small><b>{{ $selectedTask->due_at?->format('d M Y, h:i A') ?? 'No due date' }}</b></div></div>
            <div class="tm-meta-block"><span>Project</span><b>{{ $selectedTask->project?->name ?? 'No project' }}</b></div>
            @if($selectedTask->related_type && $selectedTask->related_id)<div class="tm-meta-block"><span>Linked record</span><b>{{ class_basename($selectedTask->related_type) }} #{{ $selectedTask->related_id }}</b></div>@endif
            @can('watch',$selectedTask)<form method="POST" action="{{ route('collaboration.tasks.watcher.update',$selectedTask) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="toggle"><button class="tm-watchbtn {{ $watcherIds->contains(auth()->id()) ? 'on' : '' }}" type="submit"><i class="fa-solid fa-eye"></i>{{ $watcherIds->contains(auth()->id()) ? 'Watching' : 'Watch' }}</button></form>@endcan
        </aside>
    </div>
</aside>

@if(!$selectedTask->assigned_to_user_id)
    @can('assign',$selectedTask)
        <div class="tm-assignee-overlay" x-show="assigneeOpen" x-cloak x-on:click.outside="assigneeOpen=false" x-data="peopleSearch" role="dialog" aria-modal="true" aria-label="Assign task">
            <header><div><b>Assign task</b><small>Choose an authorized employee</small></div><button class="tm-iconbtn" type="button" x-on:click="assigneeOpen=false" aria-label="Close assignee picker"><i class="fa-solid fa-xmark"></i></button></header>
            <label class="tm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search name, email, role or department" x-on:input="filterPeople($event)" oninput="if (window.filterPeople) window.filterPeople(event);" autofocus></label>
            <div class="tm-people-pop-list">
                @forelse($users as $userOption)
                    <form method="POST" action="{{ route('collaboration.tasks.assign',$selectedTask) }}" data-person-search="{{ strtolower($userOption->name.' '.$userOption->email.' '.($userOption->role?->name ?? '').' '.($userOption->employee?->department ?? '')) }}">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}"><input type="hidden" name="assigned_to_user_id" value="{{ $userOption->id }}"><button type="submit"><span class="tm-card-owner">{{ strtoupper(substr($userOption->name,0,1)) }}</span><span><b>{{ $userOption->name }}</b><small>{{ $userOption->role?->name }} · {{ $userOption->employee?->department ?? $userOption->email }}</small></span></button></form>
                @empty
                    <p class="tm-empty-copy">No employees are available for assignment.</p>
                @endforelse
            </div>
        </div>
    @endcan
@endif

@can('requestTransfer',$selectedTask)
    <div class="tm-modal-scrim tm-transfer-scrim" x-show="transferOpen" x-cloak>
        <section class="tm-modal tm-transfer-modal" role="dialog" aria-modal="true" aria-labelledby="task-transfer-title">
            <header class="tm-modal-head"><div><h2 id="task-transfer-title"><i class="fa-solid fa-right-left"></i> Transfer task</h2><p>{{ $selectedTask->task_number }} · {{ $selectedTask->title }}</p></div><button type="button" class="tm-iconbtn" x-on:click="closeTransfer" aria-label="Close transfer"><i class="fa-solid fa-xmark"></i></button></header>
            <form method="POST" action="{{ route('collaboration.tasks.transfer-requests.store',$selectedTask) }}" x-data="taskTransferForm" x-on:change="sync" x-on:input="sync">@csrf
                <input type="hidden" name="form_context" value="transfer">
                <input type="hidden" name="lock_version" value="{{ $selectedTask->lock_version }}">
                <div class="tm-modal-body">
                    <div class="tm-current-owner"><span>Current assignee</span><b class="tm-person-line"><span class="tm-card-owner">{{ strtoupper(substr($selectedTask->assignedTo?->name ?? 'U',0,1)) }}</span>{{ $selectedTask->assignedTo?->name ?? 'Unassigned' }}</b></div>
                    <div class="tm-transfer-behaviour"><i class="fa-solid {{ $transferRequiresApproval ? 'fa-shield-halved' : 'fa-bolt' }}"></i><span><b>{{ $transferRequiresApproval ? 'Manager approval required' : 'Immediate reassignment' }}</b><small>{{ $transferRequiresApproval ? 'The task moves only after an authorized approver accepts this request.' : 'The selected employee becomes the assignee when you submit.' }}</small></span></div>
                    <fieldset class="people-search-picker" x-data="peopleSearch"><legend>Transfer to *</legend><label class="tm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search employee name, role or department" x-on:input="filterPeople($event)" oninput="if (window.filterPeople) window.filterPeople(event);"></label><div class="people-search-results">@foreach($users->where('id','!=',$selectedTask->assigned_to_user_id) as $userOption)<label data-person-search="{{ strtolower($userOption->name.' '.$userOption->email.' '.($userOption->role?->name ?? '').' '.($userOption->employee?->department ?? '')) }}"><input type="radio" name="assigned_to_user_id" value="{{ $userOption->id }}" required><span><b>{{ $userOption->name }}</b><small>{{ $userOption->role?->name }} · {{ $userOption->employee?->department ?? $userOption->email }}</small></span></label>@endforeach</div></fieldset>
                    <label class="tm-field"><span>Reason *</span><textarea class="tm-textarea" name="reason" maxlength="1000" required placeholder="Why is this task being transferred?">{{ old('reason') }}</textarea></label>
                </div>
                <footer class="tm-modal-foot"><button type="button" class="blade-secondary-action" x-on:click="closeTransfer">Cancel</button><button class="blade-primary-action" type="submit" x-bind:disabled="!ready"><i class="fa-solid fa-right-left"></i> {{ $transferRequiresApproval ? 'Request transfer' : 'Transfer task' }}</button></footer>
            </form>
        </section>
    </div>
@endcan
</div>
</template>

<script>
function validateTaskAttachmentInput(input) {
    const errorDiv = document.getElementById('tm-file-js-error');
    const labelSpan = document.getElementById('tm-file-label-text');
    const defaultLabel = 'Choose a file (Images, PDF, Office, ZIP up to 5MB)';
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.innerHTML = '';
    }
    if (!input.files || !input.files[0]) {
        if (labelSpan) labelSpan.innerText = defaultLabel;
        return;
    }

    const file = input.files[0];

    // Check if video file selected
    if (file.type.startsWith('video/') || /\.(mp4|mov|avi|webm|mkv|3gp|flv|wmv)$/i.test(file.name)) {
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Video files are not allowed. Please select an image or document under 5 MB.';
            errorDiv.style.display = 'block';
        }
        input.value = '';
        if (labelSpan) labelSpan.innerText = defaultLabel;
        return;
    }

    // Check file size (5 MB max)
    const maxSizeBytes = 5 * 1024 * 1024; // 5 MB
    if (file.size > maxSizeBytes) {
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> File size limit exceeded (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB). Only files up to 5 MB are allowed.';
            errorDiv.style.display = 'block';
        }
        input.value = '';
        if (labelSpan) labelSpan.innerText = defaultLabel;
        return;
    }

    if (labelSpan) {
        labelSpan.innerText = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    }
}
</script>
