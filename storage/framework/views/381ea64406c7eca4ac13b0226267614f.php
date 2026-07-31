<?php $__env->startSection('title', '419 - Page Expired'); ?>

<?php $__env->startSection('glow-color-1', '#F59E0B'); ?>
<?php $__env->startSection('glow-color-2', '#EF4444'); ?>

<?php $__env->startSection('code-gradient', 'linear-gradient(135deg, #FBBF24 0%, #F87171 100%)'); ?>
<?php $__env->startSection('icon-bg', 'rgba(245, 158, 11, 0.15)'); ?>
<?php $__env->startSection('icon-border', 'rgba(245, 158, 11, 0.3)'); ?>
<?php $__env->startSection('icon-color', '#FBBF24'); ?>
<?php $__env->startSection('btn-bg', 'linear-gradient(135deg, #D97706 0%, #DC2626 100%)'); ?>

<?php $__env->startSection('icon'); ?>
    <i class="fa-solid fa-clock-rotate-left"></i>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('code', '419'); ?>
<?php $__env->startSection('headline', 'Page Session Expired'); ?>
<?php $__env->startSection('message', 'Your session timed out due to inactivity or a security token mismatch. Refresh the page to log back in or continue your work.'); ?>

<?php $__env->startSection('actions'); ?>
    <button type="button" class="btn-primary" onclick="window.location.reload()">
        <i class="fa-solid fa-rotate"></i> Refresh Page
    </button>
    <a href="<?php echo e(url('/')); ?>" class="btn-secondary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/errors/419.blade.php ENDPATH**/ ?>