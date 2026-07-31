<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" data-theme="<?php echo e($theme ?? 'light'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title', 'Builder360 ERP CRM'); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/enterprise.css', 'resources/js/app.js']); ?>
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body
    class="b360-classic"
    x-data="builderShell"
    x-bind:class="navigationClasses"
    x-on:keydown.escape.window="handleEscape"
    x-on:resize.window="handleResize"
>
    <?php echo $__env->make('partials.brij-loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="b360-shell">
        <?php echo $__env->make('builder360.classic.partials.sidebar', ['shell' => $shell], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <button type="button" class="b360-nav-backdrop" x-on:click="closeNavigation" aria-label="Close navigation" tabindex="-1"></button>

        <div class="b360-main">
            <?php echo $__env->make('builder360.classic.partials.topbar', ['shell' => $shell], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <main class="b360-content">
                <?php echo $__env->make('builder360.classic.partials.flash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->yieldContent('content'); ?>
            </main>
        </div>
    </div>

    <style>
        .people-search-results > label.is-hidden,
        .people-search-results label.is-hidden,
        [data-person-search].is-hidden {
            display: none !important;
        }
    </style>
    <script>
        window.filterPeople = function(event) {
            const input = event?.currentTarget || event?.target;
            if (! input) return;

            const picker = input.closest('.people-search-picker') || input.closest('[x-data="peopleSearch"]') || input.closest('.tm-assignee-overlay') || input.closest('fieldset') || input.closest('details') || input.closest('.cal-attendee-picker');
            if (! picker) return;

            const query = String(input.value || '').trim().toLowerCase();
            const words = query.split(/\s+/).filter(Boolean);

            picker.querySelectorAll('[data-person-search]').forEach(function(row) {
                const haystack = String(row.getAttribute('data-person-search') || row.dataset.personSearch || '').toLowerCase();
                const matches = words.length === 0 || words.every(function(w) { return haystack.includes(w); });
                row.hidden = ! matches;
                if (matches) {
                    row.classList.remove('is-hidden');
                    row.style.setProperty('display', '', 'important');
                } else {
                    row.classList.add('is-hidden');
                    row.style.setProperty('display', 'none', 'important');
                }
            });
        };
    </script>
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/developer/public_html/build365/resources/views/layouts/builder360-classic.blade.php ENDPATH**/ ?>