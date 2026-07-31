<div class="b360-thread-timeline" x-ref="timeline" aria-live="polite" x-on:click="handleTimelineClick($event)" onclick="if (window.handleTimelineClick) window.handleTimelineClick(event);">
    <?php $__empty_1 = true; $__currentLoopData = $chatMessages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $isMine = (int) $message->sender_user_id === (int) auth()->id();
            $reactionGroups = $message->reactions->groupBy('emoji');
            $otherReads = $message->reads->where('user_id', '!=', auth()->id());
            $allRead = $otherReads->isNotEmpty() && $otherReads->every(fn ($read) => $read->read_at !== null);
        ?>
        <article class="b360-thread-message <?php echo e($isMine ? 'is-mine' : ''); ?>" data-message-id="<?php echo e($message->id); ?>">
            <?php if (isset($component)) { $__componentOriginal2252ef3298868bc9de4c534a2a83a2a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.user-avatar','data' => ['user' => $message->sender,'label' => $message->sender?->name ?? 'System','class' => 'b360-message-avatar']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->sender),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($message->sender?->name ?? 'System'),'class' => 'b360-message-avatar']); ?>
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
            <div class="b360-message-content">
          
                
                <?php
                    $parentMsg = $message->parent ?? ($message->parent_message_id ? \App\Models\ChatMessage::with('sender')->find($message->parent_message_id) : null);
                ?>
                <?php if($parentMsg): ?>
                    <div class="b360-chat-reply">
                        <strong><i class="fa-solid fa-reply" style="font-size:10px; margin-right:4px; opacity:0.8;"></i><?php echo e($parentMsg->sender?->name ?? 'Message'); ?></strong>
                        <span><?php echo e($parentMsg->body ? str($parentMsg->body)->squish()->limit(90) : 'Attachment'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if($message->body): ?>
                    <div class="b360-message-bubble"><?php echo e($message->body); ?></div>
                <?php endif; ?>

                <?php $__currentLoopData = $message->attachments ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="b360-chat-attachment <?php echo e($attachment->scan_status === 'blocked' ? 'is-blocked' : ''); ?>">
                        <?php if($attachment->scan_status === 'blocked'): ?>
                            <strong>Attachment unavailable</strong>
                            <small>This file did not pass the security check.</small>
                        <?php else: ?>
                            <?php if(str_starts_with((string) $attachment->mime_type, 'image/')): ?>
                                <a href="<?php echo e(route('collaboration.chat.attachments.preview', $attachment)); ?>" target="_blank" rel="noopener">
                                    <img src="<?php echo e(route('collaboration.chat.attachments.preview', $attachment)); ?>" alt="<?php echo e($attachment->original_filename); ?>">
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo e(route('collaboration.chat.attachments.download', $attachment)); ?>"><?php echo e($attachment->original_filename); ?></a>
                            <small><?php echo e(number_format(((int) $attachment->size_bytes) / 1024, 1)); ?> KB</small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                <?php if($message->poll): ?>
                    <div class="b360-chat-poll">
                        <strong><?php echo e($message->poll->question); ?></strong>
                        <?php if($message->poll->status === 'open'): ?>
                            <form method="POST" action="<?php echo e(route('collaboration.chat.polls.votes.store', $message->poll)); ?>" x-on:submit.prevent="submitTimelineAction($event)" onsubmit="if (window.submitTimelineAction) window.submitTimelineAction(event);">
                                <?php echo csrf_field(); ?>
                                <?php $__currentLoopData = $message->poll->options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <label>
                                        <input type="<?php echo e($message->poll->allows_multiple ? 'checkbox' : 'radio'); ?>" name="option_ids[]" value="<?php echo e($option->id); ?>" <?php if(! $message->poll->allows_multiple): echo 'required'; endif; ?>>
                                        <span><?php echo e($option->option_text); ?></span>
                                        <small><?php echo e($option->votes?->count() ?? 0); ?></small>
                                    </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <button class="b360-small-btn" type="submit">Vote</button>
                            </form>
                            <?php if((int) $message->poll->created_by_user_id === (int) auth()->id()): ?>
                                <form method="POST" action="<?php echo e(route('collaboration.chat.polls.close', $message->poll)); ?>" x-on:submit.prevent="submitTimelineAction($event)" onsubmit="if (window.submitTimelineAction) window.submitTimelineAction(event);">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <button class="b360-small-btn" type="submit">Close poll</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="blade-status-pill">Closed</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if($reactionGroups->isNotEmpty()): ?>
                    <div class="b360-chat-reaction-summary">
                        <?php $__currentLoopData = $reactionGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emoji => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span><?php echo e($emoji); ?> <?php echo e($rows->count()); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>

                <header style="margin-top: 5px">
                    <small><?php echo e($message->sender?->name ?? 'System'); ?></small>
                    <small><?php echo e($message->created_at?->format('h:i A')); ?></small>
                </header>
                <footer class="b360-chat-message-actions" aria-label="Message reactions"style="margin-top: 5px !important">
                    <button
                        type="button"
                        class="b360-chat-reply-action"
                        data-message-id="<?php echo e($message->id); ?>"
                        data-message-label="<?php echo e($message->message_number); ?>"
                        data-message-sender="<?php echo e($message->sender?->name ?? 'Message'); ?>"
                        data-message-body="<?php echo e(str($message->body ?? 'Attachment')->squish()->limit(90)); ?>"
                        x-on:click.stop="selectReply($event)"
                        onclick="if (window.selectReply) window.selectReply(event);"
                        aria-label="Reply to <?php echo e($message->message_number); ?>"
                        title="Reply"
                    ><i class="fa-solid fa-reply" aria-hidden="true"></i></button>
                    <?php $__currentLoopData = ['👍', '❤️', '✅']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emoji): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <form method="POST" action="<?php echo e(route('collaboration.chat.messages.reactions.update', $message)); ?>" x-on:submit.prevent="submitTimelineAction($event)" onsubmit="if (window.submitTimelineAction) window.submitTimelineAction(event);">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="emoji" value="<?php echo e($emoji); ?>">
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit" aria-label="React <?php echo e($emoji); ?>"><?php echo e($emoji); ?></button>
                        </form>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <?php if($isMine || (auth()->user()?->hasPermission('*') || ($selectedConversation && $selectedConversation->membershipFor(auth()->user())?->can_manage_members))): ?>
                        <form method="POST" action="<?php echo e(route('collaboration.chat.messages.destroy', $message)); ?>" x-on:submit.prevent="deleteMessage($event)" onsubmit="if (window.deleteMessage) window.deleteMessage(event);">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="b360-chat-delete-action" title="Delete message" aria-label="Delete message" style="background:none; border:none; color:#EF4444; cursor:pointer; padding:2px 5px; opacity:0.7;" onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=0.7">
                                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if($isMine): ?>
                        <span class="b360-chat-read-state" title="<?php echo e($allRead ? 'Read' : 'Delivered'); ?>"><?php echo e($allRead ? '✓✓' : '✓'); ?></span>
                    <?php endif; ?>
                </footer>
             
            </div>
        </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="b360-collab-empty b360-collab-empty-large">
            <strong>No messages yet</strong>
            <span>Send the first message in this conversation.</span>
        </div>
    <?php endif; ?>
</div>

<?php /**PATH /home/developer/public_html/build365/resources/views/collaboration/chat/partials/timeline.blade.php ENDPATH**/ ?>