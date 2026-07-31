<?php $__env->startSection('title', '500 - Server Error'); ?>

<?php $__env->startSection('glow-color-1', '#8B5CF6'); ?>
<?php $__env->startSection('glow-color-2', '#EC4899'); ?>

<?php $__env->startSection('code-gradient', 'linear-gradient(135deg, #A78BFA 0%, #F472B6 100%)'); ?>
<?php $__env->startSection('icon-bg', 'rgba(139, 92, 246, 0.15)'); ?>
<?php $__env->startSection('icon-border', 'rgba(139, 92, 246, 0.3)'); ?>
<?php $__env->startSection('icon-color', '#A78BFA'); ?>
<?php $__env->startSection('btn-bg', 'linear-gradient(135deg, #7C3AED 0%, #DB2777 100%)'); ?>

<?php $__env->startSection('icon'); ?>
    <i class="fa-solid fa-triangle-exclamation"></i>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('code', '500'); ?>
<?php $__env->startSection('headline', 'Internal Server Error'); ?>
<?php $__env->startSection('message', 'An unexpected server error occurred while processing your request. Please try again or return to the dashboard while our engineers investigate.'); ?>

<?php $__env->startSection('actions'); ?>
    <button type="button" class="btn-primary" onclick="window.location.reload()">
        <i class="fa-solid fa-rotate-right"></i> Try Again
    </button>
    <a href="<?php echo e(url('/')); ?>" class="btn-secondary">
        <i class="fa-solid fa-house"></i> Go to Dashboard
    </a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/errors/500.blade.php ENDPATH**/ ?>