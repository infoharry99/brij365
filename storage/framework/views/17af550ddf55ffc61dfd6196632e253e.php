<?php
    $directConversations = $conversations->where('type', 'direct_message');
    $groupConversations = $conversations->where('type', 'group_chat');
    $channelConversations = $conversations->whereNotIn('type', ['direct_message', 'group_chat']);
?>

<?php if(filled($filterQuery['q'] ?? null)): ?>
    <div class="cc-search-summary">
        <span>
            <strong><?php echo e($conversations->count()); ?></strong>
            <?php echo e(Str::plural('result', $conversations->count())); ?> for
            &ldquo;<em><?php echo e($filterQuery['q']); ?></em>&rdquo;
        </span>
        <a href="<?php echo e(route('collaboration.chat.index', collect($filterQuery)->except(['q', 'conversation_id'])->all())); ?>">
            Clear
        </a>
    </div>
<?php endif; ?>

<?php if($directConversations->isNotEmpty()): ?>
    <div class="cc-section-head">
        <p class="cc-section-label">Direct Messages</p>
        <span class="cc-section-count"><?php echo e($directConversations->count()); ?></span>
    </div>
    <?php $__currentLoopData = $directConversations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conversation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo $__env->make('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>

<?php if($groupConversations->isNotEmpty()): ?>
    <div class="cc-section-head">
        <p class="cc-section-label">Groups</p>
        <span class="cc-section-count"><?php echo e($groupConversations->count()); ?></span>
    </div>
    <?php $__currentLoopData = $groupConversations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conversation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo $__env->make('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>

<?php if($channelConversations->isNotEmpty()): ?>
    <div class="cc-section-head">
        <p class="cc-section-label">Channels</p>
        <span class="cc-section-count"><?php echo e($channelConversations->count()); ?></span>
    </div>
    <?php $__currentLoopData = $channelConversations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $conversation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo $__env->make('collaboration.chat.partials.conversation-row', [
            'conversation' => $conversation,
            'selectedConversation' => $selectedConversation,
            'filterQuery' => $filterQuery,
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>

<?php if($conversations->isEmpty()): ?>
    <div class="cc-empty">
        <span class="cc-empty-icon" aria-hidden="true"><i class="fa-regular fa-comment-dots"></i></span>
        <strong><?php echo e(filled($filterQuery['q'] ?? null) ? 'No matching conversations' : 'No conversations yet'); ?></strong>
        <span><?php echo e(filled($filterQuery['q'] ?? null) ? 'Check the spelling or clear the search to view all.' : 'Try a different filter or start a new conversation.'); ?></span>
    </div>
<?php endif; ?>
<?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/chat/partials/conversation-list.blade.php ENDPATH**/ ?>