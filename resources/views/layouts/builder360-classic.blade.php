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
                const shell = window.Alpine && document.body ? window.Alpine.$data(document.body) : null;
                if (shell && typeof shell.toggleSidebar === 'function') {
                    shell.toggleSidebar();
                    return;
                }

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
    <!-- Firebase Web FCM Push Notifications -->
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.23.0/firebase-messaging-compat.js"></script>
    <script>
        (function() {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) return;

            const firebaseConfig = {
                apiKey: "AIzaSyA9oKk0cFtqyfxaVqHDMrl644dDlaka1LU",
                projectId: "brijchat-6d93f",
                messagingSenderId: "589664366975",
                appId: "1:589664366975:web:builder360"
            };

            try {
                if (!firebase.apps.length) {
                    firebase.initializeApp(firebaseConfig);
                }
                const messaging = firebase.messaging();

                navigator.serviceWorker.register('/firebase-messaging-sw.js').then((registration) => {
                    messaging.useServiceWorker(registration);

                    if (Notification.permission === 'granted') {
                        initWebFcm(messaging);
                    } else if (Notification.permission !== 'denied') {
                        Notification.requestPermission().then((permission) => {
                            if (permission === 'granted') {
                                initWebFcm(messaging);
                            }
                        });
                    }
                }).catch(function(err) { console.warn('FCM SW registration error:', err); });

                messaging.onMessage((payload) => {
                    const title = payload.notification?.title || payload.data?.title || 'New Chat Message';
                    const body = payload.notification?.body || payload.data?.body || '';
                    if (Notification.permission === 'granted') {
                        new Notification(title, { body: body, icon: '/favicon.ico', data: payload.data });
                    }
                });

                function initWebFcm(msg) {
                    const vapidKey = "{{ config('services.fcm.vapid_key') }}";
                    const tokenOptions = vapidKey ? { vapidKey: vapidKey } : undefined;

                    msg.getToken(tokenOptions).then((currentToken) => {
                        if (currentToken) {
                            fetch('/api/auth/fcm-token', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                                },
                                body: JSON.stringify({ fcm_token: currentToken })
                            }).catch(function(err) { console.warn('FCM token save error:', err); });
                        } else {
                            console.info('[FCM Web] No registration token available. Request permission to generate one.');
                        }
                    }).catch(function(err) {
                        console.warn('[FCM Web] Token retrieval error:', err);
                        console.info('[FCM Web Tip] If using Firebase Web Push, set FCM_VAPID_KEY in .env from Firebase Console > Cloud Messaging > Web configuration > Web Push certificates.');
                    });
                }
            } catch(e) {
                console.warn('FCM Web Init error:', e);
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
