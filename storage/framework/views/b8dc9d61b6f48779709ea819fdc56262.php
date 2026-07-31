<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'user' => null,
    'label' => null,
    'class' => 'b360-avatar',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'user' => null,
    'label' => null,
    'class' => 'b360-avatar',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $name = $user?->name ?? $label ?? 'User';
    $initial = str($name)->substr(0, 1)->upper();
?>

<?php if($user?->profile_photo_path): ?>
    <img
        <?php echo e($attributes->class([$class, 'b360-user-avatar-image'])); ?>

        src="<?php echo e(route('builder360.profile-photo.show', $user)); ?>"
        alt="<?php echo e($name); ?>"
        loading="lazy"
        decoding="async"
    >
<?php else: ?>
    <span <?php echo e($attributes->class([$class])); ?> aria-label="<?php echo e($name); ?>"><?php echo e($initial); ?></span>
<?php endif; ?>
<?php /**PATH /home/developer/public_html/build365/resources/views/components/ui/user-avatar.blade.php ENDPATH**/ ?>