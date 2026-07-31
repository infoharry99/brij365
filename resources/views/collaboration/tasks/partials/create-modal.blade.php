@php
    $selectedTemplate = collect($taskTemplates)->firstWhere('id', $filters['template'] ?? null);
    $templateSteps = $selectedTemplate['steps'] ?? [];
    $departments = $users->pluck('employee.department')->filter()->unique()->sort()->values();
    $selectedAssignee = $users->firstWhere('id', (int) old('assigned_to_user_id'));
    $advancedOpen = old('metadata.recurrence_frequency', 'none') !== 'none'
        || filled(old('metadata.recurrence_until'))
        || $errors->hasAny(['metadata.recurrence_frequency', 'metadata.recurrence_interval', 'metadata.recurrence_until', 'metadata.reminder_minutes_before']);
@endphp
<template x-teleport="body">
<div class="tm-modal-scrim" x-show="createOpen" x-cloak role="presentation">
    <section class="tm-modal" role="dialog" aria-modal="true" aria-labelledby="task-create-title">
        <header class="tm-modal-head"><div><h2 id="task-create-title">Create task</h2><p>Assign clear ownership, priority and delivery dates.</p></div><button type="button" class="tm-iconbtn" x-on:click="closeCreate" aria-label="Close task form"><i class="fa-solid fa-xmark"></i></button></header>
        @if($canCreateTask)
        <form method="POST" action="{{ route('collaboration.tasks.store') }}">@csrf
            <input type="hidden" name="form_context" value="create">
            <input type="hidden" name="client_token" value="{{ old('client_token', (string) \Illuminate\Support\Str::uuid()) }}">
            @if($selectedTemplate)<input type="hidden" name="template_id" value="{{ $selectedTemplate['id'] }}">@endif
            <div class="tm-modal-body"><div class="tm-form-grid">
                @if($companies->count()>1)<x-forms.company-context :companies="$companies" placeholder="Auto from project or assignee" />@endif
                <label class="tm-field full"><span>Task title *</span><input class="tm-input" name="title" maxlength="255" required value="{{ old('title',$selectedTemplate ? ($selectedTemplate['name'].' — ') : '') }}" placeholder="What needs to be done?">@error('title')<small class="tm-field-error">{{ $message }}</small>@enderror</label>
                <label class="tm-field full"><span>Description</span><textarea class="tm-textarea" name="description" maxlength="5000" placeholder="Add detail, context and acceptance criteria...">{{ old('description',$selectedTemplate['desc'] ?? '') }}</textarea>@error('description')<small class="tm-field-error">{{ $message }}</small>@enderror</label>
                <label class="tm-field"><span>Category</span><select class="tm-select" name="module_context"><option value="">General</option>@foreach($moduleContexts as $value=>$label)<option value="{{ $value }}" @selected(old('module_context',$selectedTemplate['cat'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label class="tm-field"><span>Department</span><select class="tm-select" name="metadata[department]"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department }}" @selected(old('metadata.department')===$department)>{{ $department }}</option>@endforeach</select></label>
                <label class="tm-field"><span>Project</span><select class="tm-select" name="project_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id')==$project->id)>{{ $project->code }} · {{ $project->name }}</option>@endforeach</select></label>
                <label class="tm-field"><span>Estimated hours</span><input class="tm-input" type="number" min="0" max="9999" step="0.25" name="metadata[estimated_hours]" value="{{ old('metadata.estimated_hours') }}"></label>
                <label class="tm-field"><span>Start date</span><input class="tm-input" type="date" name="metadata[planned_start_at]" value="{{ old('metadata.planned_start_at') }}"></label>
                <label class="tm-field"><span>Due date</span><input class="tm-input" type="datetime-local" name="due_at" value="{{ old('due_at') }}">@error('due_at')<small class="tm-field-error">{{ $message }}</small>@enderror</label>
                <div class="tm-field full"><span>Priority</span><div class="tm-prichip-row">@foreach($priorities as $value=>$label)<label class="tm-prichip-choice"><input type="radio" name="priority" value="{{ $value }}" @checked(old('priority','medium')===$value)><span class="tm-prichip is-{{ $value }}">{{ $label }}</span></label>@endforeach</div></div>
                <details class="tm-people-select full" x-data="{ selectedCount: {{ count((array) old('assigned_to_user_ids', $selectedAssignee ? [$selectedAssignee->id] : [])) }} }" @if($errors->has('assigned_to_user_id') || $errors->has('assigned_to_user_ids')) open @endif>
                    <summary><span><small>Assign to (select one or more)</small><b x-text="selectedCount > 0 ? (selectedCount === 1 ? '1 user selected' : selectedCount + ' users selected') : 'Unassigned'">{{ $selectedAssignee?->name ?? 'Unassigned' }}</b></span><i class="fa-solid fa-chevron-down"></i></summary>
                    <fieldset class="people-search-picker" x-data="peopleSearch"><legend class="sr-only">Choose assignees</legend><label class="tm-search people-search-input"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search employee name, role or department..." x-on:input="filterPeople($event)" oninput="if (window.filterPeople) window.filterPeople(event);"></label><div class="people-search-results">@foreach($users as $userOption)<label data-person-search="{{ strtolower($userOption->name.' '.$userOption->email.' '.($userOption->role?->name ?? '').' '.($userOption->employee?->department ?? '')) }}"><input type="checkbox" name="assigned_to_user_ids[]" value="{{ $userOption->id }}" @checked(in_array($userOption->id, (array) old('assigned_to_user_ids', $selectedAssignee ? [$selectedAssignee->id] : []))) x-on:change="selectedCount = $el.closest('form').querySelectorAll('input[name=\'assigned_to_user_ids[]\']:checked').length"><span><b>{{ $userOption->name }}</b><small>{{ $userOption->role?->name }} · {{ $userOption->employee?->department ?? $userOption->email }}</small></span></label>@endforeach</div></fieldset>
                </details>
                <details class="tm-advanced full" @if($advancedOpen) open @endif>
                    <summary><span><i class="fa-solid fa-rotate"></i><b>Repeat & reminders</b><small>Optional recurring schedule and due-date alerts</small></span><i class="fa-solid fa-chevron-down"></i></summary>
                    <div class="tm-advanced-grid">
                        <label class="tm-field"><span>Recurrence</span><select class="tm-select" name="metadata[recurrence_frequency]"><option value="none">Does not repeat</option>@foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $value=>$label)<option value="{{ $value }}" @selected(old('metadata.recurrence_frequency')===$value)>{{ $label }}</option>@endforeach</select></label>
                        <label class="tm-field"><span>Repeat interval</span><input class="tm-input" type="number" min="1" max="12" name="metadata[recurrence_interval]" value="{{ old('metadata.recurrence_interval',1) }}" aria-describedby="task-repeat-help"><small id="task-repeat-help">Every 1–12 selected periods.</small></label>
                        <label class="tm-field"><span>Repeat until</span><input class="tm-input" type="date" name="metadata[recurrence_until]" value="{{ old('metadata.recurrence_until') }}"></label>
                        <fieldset class="tm-field"><legend>Reminders</legend><div class="tm-check-options">@foreach([60=>'1 hour before',1440=>'1 day before',2880=>'2 days before',10080=>'1 week before'] as $minutes=>$label)<label><input type="checkbox" name="metadata[reminder_minutes_before][]" value="{{ $minutes }}" @checked(in_array($minutes,old('metadata.reminder_minutes_before',[60,1440])))> {{ $label }}</label>@endforeach</div></fieldset>
                    </div>
                </details>
                @foreach($templateSteps as $index=>$step)<input type="hidden" name="checklist[{{ $index }}][label]" value="{{ $step }}"><input type="hidden" name="checklist[{{ $index }}][done]" value="0">@endforeach
            </div></div>
            <footer class="tm-modal-foot"><span class="tm-modal-note">The task will appear in To Do after saving.</span><button type="button" class="blade-secondary-action" x-on:click="closeCreate">Cancel</button><button type="submit" class="blade-primary-action"><i class="fa-solid fa-plus"></i> Create task</button></footer>
        </form>
        @else<div class="tm-modal-body"><p class="tm-empty-copy">Task creation is not available for this role.</p></div>@endif
    </section>
</div>
</template>
