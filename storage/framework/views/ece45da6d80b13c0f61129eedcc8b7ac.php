<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'tone' => 'success',
    'title' => null,
    'dismissible' => false,
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
    'tone' => 'success',
    'title' => null,
    'dismissible' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<section
    <?php echo e($attributes->class(['blade-alert', 'blade-alert-'.$tone])); ?>

    role="<?php echo e(in_array($tone, ['danger', 'error'], true) ? 'alert' : 'status'); ?>"
    <?php if($dismissible): ?> x-data="dismissibleAlert" x-show="visible" <?php endif; ?>
>
    <div class="b360-alert-copy">
        <?php if($title): ?><strong><?php echo e($title); ?></strong><?php endif; ?>
        <?php echo e($slot); ?>

    </div>
    <?php if($dismissible): ?>
        <button type="button" class="b360-alert-dismiss" x-on:click="dismiss" aria-label="Dismiss message">×</button>
    <?php endif; ?>
</section>
<?php /**PATH /home/developer/public_html/build365/resources/views/components/ui/alert.blade.php ENDPATH**/ ?>