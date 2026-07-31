<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'companies',
    'selected' => null,
    'required' => false,
    'label' => 'Company',
    'placeholder' => 'Select company',
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
    'companies',
    'selected' => null,
    'required' => false,
    'label' => 'Company',
    'placeholder' => 'Select company',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $singleCompany = (bool) config('builder360.single_company.enabled', true);
    $configuredCode = (string) config('builder360.single_company.code', 'B360D');
    $availableCompanies = collect($companies);
    $activeCompany = $availableCompanies->firstWhere('code', $configuredCode) ?? $availableCompanies->first();
    $selectedCompanyId = old('company_id', $selected ?? $activeCompany?->id);
?>

<?php if($singleCompany && $activeCompany): ?>
    <input type="hidden" name="company_id" value="<?php echo e($activeCompany->id); ?>">
<?php else: ?>
    <label>
        <?php echo e($label); ?>

        <select name="company_id" <?php if($required): echo 'required'; endif; ?>>
            <option value=""><?php echo e($placeholder); ?></option>
            <?php $__currentLoopData = $availableCompanies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($company->id); ?>" <?php if((string) $selectedCompanyId === (string) $company->id): echo 'selected'; endif; ?>>
                    <?php echo e($company->code); ?> &middot; <?php echo e($company->name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['company_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </label>
<?php endif; ?>
<?php /**PATH /home/developer/public_html/build365/resources/views/components/forms/company-context.blade.php ENDPATH**/ ?>