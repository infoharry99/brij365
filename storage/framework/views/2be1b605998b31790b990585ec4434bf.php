<?php
    $selectedTemplate = collect($taskTemplates)->firstWhere('id', $filters['template'] ?? null);
    $templateSteps = $selectedTemplate['steps'] ?? [];
    $departments = $users->pluck('employee.department')->filter()->unique()->sort()->values();
    $selectedAssignee = $users->firstWhere('id', (int) old('assigned_to_user_id'));
    $advancedOpen = old('metadata.recurrence_frequency', 'none') !== 'none'
        || filled(old('metadata.recurrence_until'))
        || $errors->hasAny(['metadata.recurrence_frequency', 'metadata.recurrence_interval', 'metadata.recurrence_until', 'metadata.reminder_minutes_before']);
?>
<template x-teleport="body">
<div class="tm-modal-scrim" x-show="createOpen" x-cloak role="presentation">
    <section class="tm-modal" role="dialog" aria-modal="true" aria-labelledby="task-create-title">
        <header class="tm-modal-head"><div><h2 id="task-create-title">Create task</h2><p>Assign clear ownership, priority and delivery dates.</p></div><button type="button" class="tm-iconbtn" x-on:click="closeCreate" aria-label="Close task form"><i class="fa-solid fa-xmark"></i></button></header>
        <?php if($canCreateTask): ?>
        <form method="POST" action="<?php echo e(route('collaboration.tasks.store')); ?>"><?php echo csrf_field(); ?>
            <input type="hidden" name="form_context" value="create">
            <input type="hidden" name="client_token" value="<?php echo e(old('client_token', (string) \Illuminate\Support\Str::uuid())); ?>">
            <?php if($selectedTemplate): ?><input type="hidden" name="template_id" value="<?php echo e($selectedTemplate['id']); ?>"><?php endif; ?>
            <div class="tm-modal-body"><div class="tm-form-grid">
                <?php if($companies->count()>1): ?><?php if (isset($component)) { $__componentOriginal5ee006ce6757c21855df609df2a8580f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5ee006ce6757c21855df609df2a8580f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.forms.company-context','data' => ['companies' => $companies,'placeholder' => 'Auto from project or assignee']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('forms.company-context'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['companies' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($companies),'placeholder' => 'Auto from project or assignee']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5ee006ce6757c21855df609df2a8580f)): ?>
<?php $attributes = $__attributesOriginal5ee006ce6757c21855df609df2a8580f; ?>
<?php unset($__attributesOriginal5ee006ce6757c21855df609df2a8580f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5ee006ce6757c21855df609df2a8580f)): ?>
<?php $component = $__componentOriginal5ee006ce6757c21855df609df2a8580f; ?>
<?php unset($__componentOriginal5ee006ce6757c21855df609df2a8580f); ?>
<?php endif; ?><?php endif; ?>
                <label class="tm-field full"><span>Task title *</span><input class="tm-input" name="title" maxlength="255" required value="<?php echo e(old('title',$selectedTemplate ? ($selectedTemplate['name'].' — ') : '')); ?>" placeholder="What needs to be done?"><?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="tm-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <label class="tm-field full"><span>Description</span><textarea class="tm-textarea" name="description" maxlength="5000" placeholder="Add detail, context and acceptance criteria..."><?php echo e(old('description',$selectedTemplate['desc'] ?? '')); ?></textarea><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="tm-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <label class="tm-field"><span>Category</span><select class="tm-select" name="module_context"><option value="">General</option><?php $__currentLoopData = $moduleContexts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('module_context',$selectedTemplate['cat'] ?? '')===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label class="tm-field"><span>Department</span><select class="tm-select" name="metadata[department]"><option value="">No department</option><?php $__currentLoopData = $departments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $department): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($department); ?>" <?php if(old('metadata.department')===$department): echo 'selected'; endif; ?>><?php echo e($department); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label class="tm-field"><span>Project</span><select class="tm-select" name="project_id"><option value="">No project</option><?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($project->id); ?>" <?php if(old('project_id')==$project->id): echo 'selected'; endif; ?>><?php echo e($project->code); ?> · <?php echo e($project->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                <label class="tm-field"><span>Estimated hours</span><input class="tm-input" type="number" min="0" max="9999" step="0.25" name="metadata[estimated_hours]" value="<?php echo e(old('metadata.estimated_hours')); ?>"></label>
                <label class="tm-field"><span>Start date</span><input class="tm-input" type="date" name="metadata[planned_start_at]" value="<?php echo e(old('metadata.planned_start_at')); ?>"></label>
                <label class="tm-field"><span>Due date</span><input class="tm-input" type="datetime-local" name="due_at" value="<?php echo e(old('due_at')); ?>"><?php $__errorArgs = ['due_at'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="tm-field-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
                <div class="tm-field full"><span>Priority</span><div class="tm-prichip-row"><?php $__currentLoopData = $priorities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label class="tm-prichip-choice"><input type="radio" name="priority" value="<?php echo e($value); ?>" <?php if(old('priority','medium')===$value): echo 'checked'; endif; ?>><span class="tm-prichip is-<?php echo e($value); ?>"><?php echo e($label); ?></span></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div>
                <details class="tm-people-select full" x-data="{ selectedCount: <?php echo e(count((array) old('assigned_to_user_ids', $selectedAssignee ? [$selectedAssignee->id] : []))); ?> }" <?php if($errors->has('assigned_to_user_id') || $errors->has('assigned_to_user_ids')): ?> open <?php endif; ?>>
                    <summary><span><small>Assign to (select one or more)</small><b x-text="selectedCount > 0 ? (selectedCount === 1 ? '1 user selected' : selectedCount + ' users selected') : 'Unassigned'"><?php echo e($selectedAssignee?->name ?? 'Unassigned'); ?></b></span><i class="fa-solid fa-chevron-down"></i></summary>
                    <fieldset class="people-search-picker" x-data="peopleSearch"><legend class="sr-only">Choose assignees</legend><label class="tm-search people-search-input"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Search employee name, role or department..." x-on:input="filterPeople($event)" oninput="if (window.filterPeople) window.filterPeople(event);"></label><div class="people-search-results"><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $userOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label data-person-search="<?php echo e(strtolower($userOption->name.' '.$userOption->email.' '.($userOption->role?->name ?? '').' '.($userOption->employee?->department ?? ''))); ?>"><input type="checkbox" name="assigned_to_user_ids[]" value="<?php echo e($userOption->id); ?>" <?php if(in_array($userOption->id, (array) old('assigned_to_user_ids', $selectedAssignee ? [$selectedAssignee->id] : []))): echo 'checked'; endif; ?> x-on:change="selectedCount = $el.closest('form').querySelectorAll('input[name=\'assigned_to_user_ids[]\']:checked').length"><span><b><?php echo e($userOption->name); ?></b><small><?php echo e($userOption->role?->name); ?> · <?php echo e($userOption->employee?->department ?? $userOption->email); ?></small></span></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></fieldset>
                </details>
                <details class="tm-advanced full" <?php if($advancedOpen): ?> open <?php endif; ?>>
                    <summary><span><i class="fa-solid fa-rotate"></i><b>Repeat & reminders</b><small>Optional recurring schedule and due-date alerts</small></span><i class="fa-solid fa-chevron-down"></i></summary>
                    <div class="tm-advanced-grid">
                        <label class="tm-field"><span>Recurrence</span><select class="tm-select" name="metadata[recurrence_frequency]"><option value="none">Does not repeat</option><?php $__currentLoopData = ['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('metadata.recurrence_frequency')===$value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
                        <label class="tm-field"><span>Repeat interval</span><input class="tm-input" type="number" min="1" max="12" name="metadata[recurrence_interval]" value="<?php echo e(old('metadata.recurrence_interval',1)); ?>" aria-describedby="task-repeat-help"><small id="task-repeat-help">Every 1–12 selected periods.</small></label>
                        <label class="tm-field"><span>Repeat until</span><input class="tm-input" type="date" name="metadata[recurrence_until]" value="<?php echo e(old('metadata.recurrence_until')); ?>"></label>
                        <fieldset class="tm-field"><legend>Reminders</legend><div class="tm-check-options"><?php $__currentLoopData = [60=>'1 hour before',1440=>'1 day before',2880=>'2 days before',10080=>'1 week before']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $minutes=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label><input type="checkbox" name="metadata[reminder_minutes_before][]" value="<?php echo e($minutes); ?>" <?php if(in_array($minutes,old('metadata.reminder_minutes_before',[60,1440]))): echo 'checked'; endif; ?>> <?php echo e($label); ?></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></fieldset>
                    </div>
                </details>
                <?php $__currentLoopData = $templateSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index=>$step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><input type="hidden" name="checklist[<?php echo e($index); ?>][label]" value="<?php echo e($step); ?>"><input type="hidden" name="checklist[<?php echo e($index); ?>][done]" value="0"><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div></div>
            <footer class="tm-modal-foot"><span class="tm-modal-note">The task will appear in To Do after saving.</span><button type="button" class="blade-secondary-action" x-on:click="closeCreate">Cancel</button><button type="submit" class="blade-primary-action"><i class="fa-solid fa-plus"></i> Create task</button></footer>
        </form>
        <?php else: ?><div class="tm-modal-body"><p class="tm-empty-copy">Task creation is not available for this role.</p></div><?php endif; ?>
    </section>
</div>
</template>
<?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/tasks/partials/create-modal.blade.php ENDPATH**/ ?>