<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme ?? 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Builder360 ERP CRM')</title>

    @vite(['resources/css/enterprise.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body
    class="b360-classic"
    x-data="builderShell"
    x-bind:class="navigationClasses"
    x-on:keydown.escape.window="handleEscape"
    x-on:resize.window="handleResize"
>
    @include('partials.brij-loader')
    <div class="b360-shell">
        @include('builder360.classic.partials.sidebar', ['shell' => $shell])
        <button type="button" class="b360-nav-backdrop" x-on:click="closeNavigation" aria-label="Close navigation" tabindex="-1"></button>

        <div class="b360-main">
            @include('builder360.classic.partials.topbar', ['shell' => $shell])

            <main class="b360-content">
                @include('builder360.classic.partials.flash')
                @yield('content')
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
    @stack('scripts')
</body>
</html>
