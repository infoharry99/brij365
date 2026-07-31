<?php $__env->startSection('title', 'My Profile — Builder360'); ?>

<?php $__env->startSection('content'); ?>
<?php ($currentTheme = session('builder360.theme', 'light')); ?>
<section class="b360-profile-page" aria-labelledby="profile-title">
    <header class="b360-profile-hero">
        <div class="b360-profile-avatar-wrap">
            <?php if (isset($component)) { $__componentOriginal2252ef3298868bc9de4c534a2a83a2a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.user-avatar','data' => ['user' => auth()->user(),'label' => $page->name,'class' => 'b360-avatar b360-profile-avatar']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(auth()->user()),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($page->name),'class' => 'b360-avatar b360-profile-avatar']); ?>
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
        </div>
        <div class="b360-profile-identity">
            <span class="b360-profile-eyebrow">My Profile</span>
            <h1 id="profile-title"><?php echo e($page->name); ?></h1>
            <p><?php echo e($page->email); ?></p>
            <div class="b360-profile-badges" aria-label="Current account context">
                <span><i class="fa-solid fa-user-shield" aria-hidden="true"></i><?php echo e($page->activeRole); ?></span>
                <span><i class="fa-solid fa-building" aria-hidden="true"></i><?php echo e($page->companyName); ?></span>
                <span class="is-success"><i class="fa-solid fa-circle" aria-hidden="true"></i><?php echo e($page->status); ?></span>
            </div>
        </div>
        <div class="b360-profile-actions">
            <a class="blade-action" href="<?php echo e(route('builder360.dashboard')); ?>"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</a>
            <a class="blade-action" href="<?php echo e(route('notifications.index')); ?>"><i class="fa-regular fa-bell" aria-hidden="true"></i>Notifications</a>
        </div>
    </header>

    <div class="b360-profile-layout">
        <div class="b360-profile-primary">
            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-camera" aria-hidden="true"></i></span>
                    <div><span>Appearance</span><h2>Profile photo</h2></div>
                </div>
                <form
                    method="POST"
                    action="<?php echo e(route('builder360.profile-photo.update')); ?>"
                    enctype="multipart/form-data"
                    class="b360-profile-photo-form"
                    x-data="profilePhotoPicker"
                    x-on:submit="submitPhoto"
                >
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('PATCH'); ?>
                    <div class="b360-profile-photo-preview" aria-hidden="true">
                        <?php if (isset($component)) { $__componentOriginal2252ef3298868bc9de4c534a2a83a2a2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2252ef3298868bc9de4c534a2a83a2a2 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.user-avatar','data' => ['user' => auth()->user(),'label' => $page->name,'class' => 'b360-avatar']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.user-avatar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['user' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(auth()->user()),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($page->name),'class' => 'b360-avatar']); ?>
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
                        <img x-show="previewUrl" x-bind:src="previewUrl" alt="">
                    </div>
                    <div class="b360-profile-photo-copy">
                        <strong>Choose a new profile photo</strong>
                        <p>JPG, PNG, or WebP. Maximum file size 5 MB.</p>
                        <span x-show="selectedName" x-text="selectedName" class="b360-profile-file-name"></span>
                        <span x-show="error" x-text="error" class="b360-field-error" role="alert"></span>
                        <?php $__errorArgs = ['photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="b360-field-error" role="alert"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="b360-profile-photo-actions">
                        <label class="blade-action" for="profile-photo"><i class="fa-regular fa-image" aria-hidden="true"></i>Choose photo</label>
                        <input id="profile-photo" class="visually-hidden" type="file" name="photo" accept="image/jpeg,image/png,image/webp" x-on:change="choosePhoto" required>
                        <button class="blade-primary-action" type="submit" x-bind:disabled="busy">
                            <span x-show="! busy">Save photo</span><span x-show="busy">Saving…</span>
                        </button>
                    </div>
                </form>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-regular fa-id-card" aria-hidden="true"></i></span>
                    <div><span>Overview</span><h2>Account details</h2></div>
                    <small>Managed by administrator</small>
                </div>
                <dl class="b360-profile-details">
                    <div><dt>Name</dt><dd><?php echo e($page->name); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo e($page->email); ?></dd></div>
                    <div><dt>Employee</dt><dd><?php echo e($page->employeeCode); ?></dd></div>
                    <div><dt>Company</dt><dd><?php echo e($page->companyCode); ?> · <?php echo e($page->companyName); ?></dd></div>
                </dl>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span>
                    <div><span>Recent Activity</span><h2>Account activity</h2></div>
                    <small><?php echo e(count($page->recentActivity)); ?> shown</small>
                </div>
                <div class="b360-profile-activity">
                    <?php $__empty_1 = true; $__currentLoopData = $page->recentActivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="b360-profile-activity-row">
                            <span class="b360-profile-activity-dot" aria-hidden="true"></span>
                            <div><strong><?php echo e($activity->action); ?></strong><span><?php echo e($activity->event); ?></span></div>
                            <time><?php echo e($activity->occurredAt); ?></time>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="b360-profile-empty">
                            <i class="fa-regular fa-clock" aria-hidden="true"></i>
                            <strong>No recent activity</strong>
                            <span>Your account activity will appear here.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>

        <aside class="b360-profile-secondary" aria-label="Profile settings and access">
            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
                    <div><span>Account Access</span><h2>Current access</h2></div>
                </div>
                <p class="b360-profile-admin-note"><i class="fa-solid fa-lock" aria-hidden="true"></i>Roles, permissions, and project access are managed by your administrator.</p>
                <dl class="b360-profile-details">
                    <div><dt>Assigned role</dt><dd><?php echo e($page->assignedRole); ?></dd></div>
                    <div><dt>Role preview</dt><dd><?php echo e($page->activeRole); ?></dd></div>
                    <div><dt>Access level</dt><dd><?php echo e($page->accessLevel); ?></dd></div>
                    <div><dt>Access rules</dt><dd><?php echo e(number_format($page->permissionCount)); ?> assigned</dd></div>
                    <div><dt>Project view</dt><dd><?php echo e($page->projectContext); ?></dd></div>
                </dl>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-palette" aria-hidden="true"></i></span>
                    <div><span>Preferences</span><h2>Theme</h2></div>
                </div>
                <div class="b360-profile-preference">
                    <div><strong><?php echo e(ucfirst($currentTheme)); ?> appearance</strong><span>Applied across Builder360 for this session.</span></div>
                    <form method="POST" action="<?php echo e(route('builder360.theme.store')); ?>" x-on:submit="changeTheme">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="theme" value="<?php echo e($currentTheme === 'dark' ? 'light' : 'dark'); ?>">
                        <button class="blade-action" type="submit" aria-label="Switch to <?php echo e($currentTheme === 'dark' ? 'light' : 'dark'); ?> theme" x-bind:aria-label="themeLabel" x-bind:disabled="themeBusy">
                            <i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i>Switch theme
                        </button>
                        <span class="b360-form-error" aria-live="polite" x-text="themeError"></span>
                    </form>
                </div>
            </article>

            <article class="b360-profile-card">
                <div class="b360-profile-card-head">
                    <span class="b360-profile-card-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <div><span>Security</span><h2>Login protection</h2></div>
                </div>
                <dl class="b360-profile-details">
                    <div><dt>Account status</dt><dd><span class="b360-profile-status is-success"><?php echo e($page->status); ?></span></dd></div>
                    <div><dt>Email verification</dt><dd><?php echo e($page->emailVerified ? 'Verified' : 'Pending verification'); ?></dd></div>
                    <div><dt>Authenticated session</dt><dd>Active</dd></div>
                    <div><dt>Password recovery</dt><dd>Available from login</dd></div>
                </dl>
                <form class="b360-profile-logout" method="POST" action="<?php echo e(route('logout')); ?>">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="blade-danger-action"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>Logout from this session</button>
                </form>
            </article>
        </aside>
    </div>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.builder360-classic', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/builder360/classic/profile.blade.php ENDPATH**/ ?>