<?php $__env->startSection('title', '404 - Page Not Found'); ?>

<?php $__env->startSection('glow-color-1', '#3B82F6'); ?>
<?php $__env->startSection('glow-color-2', '#6366F1'); ?>

<?php $__env->startSection('code-gradient', 'linear-gradient(135deg, #60A5FA 0%, #818CF8 100%)'); ?>
<?php $__env->startSection('icon-bg', 'rgba(59, 130, 246, 0.15)'); ?>
<?php $__env->startSection('icon-border', 'rgba(59, 130, 246, 0.3)'); ?>
<?php $__env->startSection('icon-color', '#60A5FA'); ?>
<?php $__env->startSection('btn-bg', 'linear-gradient(135deg, #2563EB 0%, #4F46E5 100%)'); ?>

<?php $__env->startSection('icon'); ?>
    <i class="fa-solid fa-compass"></i>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('code', '404'); ?>
<?php $__env->startSection('headline', 'Page Not Found'); ?>
<?php $__env->startSection('message', 'The page you are looking for might have been removed, renamed, or is temporarily unavailable. Check the web address or return home.'); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>" class="btn-primary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
    <button type="button" class="btn-secondary" onclick="window.history.back()">
        <i class="fa-solid fa-arrow-left"></i> Go Back
    </button>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/errors/404.blade.php ENDPATH**/ ?>