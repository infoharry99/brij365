<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'title',
    'description' => null,
    'eyebrow' => null,
    'headingId' => null,
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
    'eyebrow' => null,
    'headingId' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<header <?php echo e($attributes->class(['blade-workspace-header'])); ?>>
    <div>
        <?php if($eyebrow): ?>
            <p class="blade-dashboard-eyebrow"><?php echo e($eyebrow); ?></p>
        <?php endif; ?>
        <h1 <?php if($headingId): ?> id="<?php echo e($headingId); ?>" <?php endif; ?>><?php echo e($title); ?></h1>
        <?php if($description): ?>
            <p><?php echo e($description); ?></p>
        <?php endif; ?>
    </div>

    <?php if(isset($actions)): ?>
        <nav class="blade-workspace-actions" aria-label="Page actions">
            <?php echo e($actions); ?>

        </nav>
    <?php endif; ?>
</header>
<?php /**PATH /home/developer/public_html/build365/resources/views/components/ui/page-header.blade.php ENDPATH**/ ?>