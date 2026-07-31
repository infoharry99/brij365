(function () {
    'use strict';

    var sidebar = document.getElementById('b360Sidebar');
    var toggle = document.querySelector('[data-b360-nav-toggle]');
    var closeButtons = document.querySelectorAll('[data-b360-nav-close]');

    if (!sidebar || !toggle) {
        return;
    }

    function setOpen(open) {
        sidebar.classList.toggle('is-open', open);
        document.body.classList.toggle('b360-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            sidebar.focus();
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(!sidebar.classList.contains('is-open'));
    });

    closeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setOpen(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 860) {
            setOpen(false);
        }
    });

    document.querySelectorAll('[data-b360-people-search]').forEach(function (input) {
        var target = document.getElementById(input.getAttribute('data-b360-people-search'));

        if (!target) {
            return;
        }

        input.addEventListener('input', function () {
            var query = input.value.trim().toLowerCase();

            Array.prototype.forEach.call(target.options, function (option) {
                var searchable = (option.getAttribute('data-search') || option.textContent || '').toLowerCase();
                option.hidden = query !== '' && searchable.indexOf(query) === -1;
            });
        });
    });

    document.querySelectorAll('[data-b360-period-form]').forEach(function (form) {
        var select = form.querySelector('[data-b360-period-select]');
        var customFields = form.querySelector('[data-b360-custom-period]');

        if (!select || !customFields) {
            return;
        }

        function syncCustomPeriod() {
            var isCustom = select.value === 'custom';
            customFields.hidden = !isCustom;
            customFields.querySelectorAll('input').forEach(function (input) {
                input.required = isCustom;
            });
        }

        select.addEventListener('change', syncCustomPeriod);
        syncCustomPeriod();
    });

    document.querySelectorAll('[data-b360-context-form]').forEach(function (form) {
        var select = form.querySelector('select');

        if (select) {
            select.addEventListener('change', function () {
                form.submit();
            });
        }
    });
})();
