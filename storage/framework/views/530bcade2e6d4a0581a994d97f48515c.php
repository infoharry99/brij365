<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title',
    'description' => null,
    'icon' => 'fa-box-open',
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
    'title',
    'description' => null,
    'icon' => 'fa-box-open',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section <?php echo e($attributes->class(['b360-empty'])); ?> aria-label="<?php echo e($title); ?>">
    <i class="fa-solid <?php echo e($icon); ?>" aria-hidden="true"></i>
    <strong><?php echo e($title); ?></strong>
    <?php if($description): ?><span><?php echo e($description); ?></span><?php endif; ?>
    <?php if(isset($actions)): ?><div class="blade-workspace-actions"><?php echo e($actions); ?></div><?php endif; ?>
</section>
<?php /**PATH /home/developer/public_html/build365/resources/views/components/ui/empty-state.blade.php ENDPATH**/ ?>