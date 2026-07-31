<?php $__env->startSection('title', '403 - Access Forbidden'); ?>

<?php $__env->startSection('glow-color-1', '#F43F5E'); ?>
<?php $__env->startSection('glow-color-2', '#E11D48'); ?>

<?php $__env->startSection('code-gradient', 'linear-gradient(135deg, #FB7185 0%, #F43F5E 100%)'); ?>
<?php $__env->startSection('icon-bg', 'rgba(244, 63, 94, 0.15)'); ?>
<?php $__env->startSection('icon-border', 'rgba(244, 63, 94, 0.3)'); ?>
<?php $__env->startSection('icon-color', '#FB7185'); ?>
<?php $__env->startSection('btn-bg', 'linear-gradient(135deg, #E11D48 0%, #BE123C 100%)'); ?>

<?php $__env->startSection('icon'); ?>
    <i class="fa-solid fa-shield-halved"></i>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('code', '403'); ?>
<?php $__env->startSection('headline', 'Access Forbidden'); ?>
<?php $__env->startSection('message', 'You do not have permission to access this page or resource. Contact your system administrator if you believe your account role requires access.'); ?>

<?php $__env->startSection('actions'); ?>
    <a href="<?php echo e(url('/')); ?>" class="btn-primary">
        <i class="fa-solid fa-house"></i> Return to Dashboard
    </a>
    <button type="button" class="btn-secondary" onclick="window.history.back()">
        <i class="fa-solid fa-arrow-left"></i> Go Back
    </button>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/errors/403.blade.php ENDPATH**/ ?>