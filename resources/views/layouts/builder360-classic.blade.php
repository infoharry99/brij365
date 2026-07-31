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
    class="b360-classic sidebar-collapsed"
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
        (function() {
            const SIDEBAR_KEY = 'b360_sidebar_collapsed';

            window.toggleSidebar = function() {
                if (window.innerWidth <= 768) {
                    document.body.classList.toggle('nav-open');
                    return;
                }
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                const sidebar = document.getElementById('b360Sidebar') || document.querySelector('.b360-sidebar');
                if (sidebar) {
                    sidebar.classList.toggle('is-collapsed', isCollapsed);
                }
                try {
                    localStorage.setItem(SIDEBAR_KEY, isCollapsed ? '1' : '0');
                } catch(e) {}

                const shell = window.Alpine && document.body ? window.Alpine.$data(document.body) : null;
                if (shell) {
                    shell.sidebarCollapsed = isCollapsed;
                }
            };

            function applyInitialSidebarState() {
                if (window.innerWidth <= 768) return;
                var stored = null;
                try { stored = localStorage.getItem(SIDEBAR_KEY); } catch(e) {}
                var shouldCollapse = (stored === null || stored === undefined || stored === '1');
                const sidebar = document.getElementById('b360Sidebar') || document.querySelector('.b360-sidebar');

                if (shouldCollapse) {
                    document.body.classList.add('sidebar-collapsed');
                    if (sidebar) sidebar.classList.add('is-collapsed');
                } else {
                    document.body.classList.remove('sidebar-collapsed');
                    if (sidebar) sidebar.classList.remove('is-collapsed');
                }

                const shell = window.Alpine && document.body ? window.Alpine.$data(document.body) : null;
                if (shell) {
                    shell.sidebarCollapsed = shouldCollapse;
                }
            }

            applyInitialSidebarState();
            document.addEventListener('DOMContentLoaded', applyInitialSidebarState);
            window.addEventListener('load', applyInitialSidebarState);
        })();

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
