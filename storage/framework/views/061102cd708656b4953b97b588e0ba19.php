<?php
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
?>
<a
    href="<?php echo e($url); ?>"
    data-conversation-row
    data-conversation-id="<?php echo e($conversation->id); ?>"
    data-search="<?php echo e(str($displayTitle.' '.$preview.' '.($conversation->type ?? ''))->lower()); ?>"
    class="cc-conv-row <?php echo e($isSelected ? 'is-active' : ''); ?>"
    <?php if($isSelected): ?> aria-current="page" <?php endif; ?>
>
    <span class="cc-conv-avatar <?php echo e($isChannel ? 'is-channel' : ''); ?>" aria-hidden="true">
        <?php if($isChannel): ?>
            <span>#</span>
        <?php else: ?>
            <?php if (isset($component)) { $__componentOriginal2252ef3298868bc9de4c534a2a83a2a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.user-avatar','data' => ['user' => $avatarUser,'label' => $displayTitle,'class' => 'cc-conv-avatar-image','style' => 'display:grid;place-items:center;width:100%;height:100%;border-radius:inherit;']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($avatarUser),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($displayTitle),'class' => 'cc-conv-avatar-image','style' => 'display:grid;place-items:center;width:100%;height:100%;border-radius:inherit;']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2)): ?>
<?php $attributes = $__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2; ?>
<?php unset($__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2252ef3298868bc9de4c534a2a83a2a2)): ?>
<?php $component = $__componentOriginal2252ef3298868bc9de4c534a2a83a2a2; ?>
<?php unset($__componentOriginal2252ef3298868bc9de4c534a2a83a2a2); ?>
<?php endif; ?>
            <?php if($avatarUser?->profile_photo_path): ?>
                <span class="cc-dot" aria-label="Profile photo available"></span>
            <?php endif; ?>
        <?php endif; ?>
    </span>
    <span class="cc-conv-copy">
        <span class="cc-conv-name-row">
            <strong class="cc-conv-name"><?php echo e($displayTitle); ?></strong>
        </span>
        <span class="cc-conv-preview" data-conversation-preview><?php echo e($preview); ?></span>
    </span>
    <span class="cc-conv-meta">
        <?php if($timeLabel): ?>
            <time class="cc-conv-time"><?php echo e($timeLabel); ?></time>
        <?php endif; ?>
        <?php if(($conversation->unread_count ?? 0) > 0 && ! $isSelected): ?>
            <em class="cc-unread-badge"><?php echo e($conversation->unread_count > 99 ? '99+' : $conversation->unread_count); ?></em>
        <?php endif; ?>
    </span>
</a>
<?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/chat/partials/conversation-row.blade.php ENDPATH**/ ?>