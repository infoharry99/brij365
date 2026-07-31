<style>
    /* ── Tokens (scoped, won't leak if already defined in enterprise.css) ── */
    :root {
        --sb-w-open:      260px;
        --sb-w-closed:    64px;
        --sb-bg:          #FFFFFF;
        --sb-bg-hover:    #F0F4FA;
        --sb-border:      #E2E8F2;
        --sb-border-2:    #f5ccab;
        --sb-accent:      #F6740C;
        --sb-accent-soft: #EEF4FF;
        --sb-accent-200:  #f1b98a;
        --sb-text:        #0F172A;
        --sb-text-2:      #334155;
        --sb-text-3:      #64748B;
        --sb-text-muted:  #94A3B8;
        --sb-danger:      #EF4444;
        --sb-brand:       #F6740C;
        --sb-ease:        cubic-bezier(0.16, 1, 0.3, 1);
        --sb-r-sm:        8px;
        --sb-r-md:        10px;
        --sb-font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    /* ── Shell ── */
    .b360-sidebar {
        width: var(--sb-w-open);
        flex-shrink: 0;
        height: 100vh;
        background: var(--sb-bg);
        border-right: 1px solid var(--sb-border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        z-index: 200;
        transition: width .28s var(--sb-ease);
        font-family: var(--sb-font);
        -webkit-font-smoothing: antialiased;
    }
    .b360-sidebar::before {
        content: '';
        display: block;
        height: 3px;
        flex-shrink: 0;
        background: linear-gradient(90deg, var(--sb-brand), #818cf8);
    }

    /*
    * COLLAPSE STATE
    * Driven by  body.sidebar-collapsed  (set by Alpine toggleSidebar()).
    * This avoids any JS touching the sidebar element directly.
    */
    body.sidebar-collapsed .b360-sidebar { width: var(--sb-w-closed); }

    /* Brand */
    .b360-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--sb-border);
        flex-shrink: 0;
        min-height: 72px;
        overflow: hidden;
    }
    .b360-brand-logo-link {
        display: flex;
        align-items: center;
        text-decoration: none;
        overflow: hidden;
    }
    .b360-logo-full {
        height: 3.5rem;
        width: auto;
        max-width: 12rem;
        object-fit: contain;
        transition: opacity .2s;
    }
    .b360-logo-compact {
        display: none;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--sb-brand), #818cf8);
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        align-items: center;
        justify-content: center;
        letter-spacing: .04em;
        box-shadow: 0 4px 12px rgba(246,116,12,.28);
        flex-shrink: 0;
    }
    body.sidebar-collapsed .b360-logo-full {
        display: none !important;
    }
    body.sidebar-collapsed .b360-logo-compact {
        display: flex !important;
        margin: 0 auto;
    }
    body.sidebar-collapsed .b360-brand {
        padding: 12px 6px;
        justify-content: center;
    }
    .b360-brand-subtitle {
        font-size: 10.5px; font-weight: 600; color: var(--sb-text-muted);
        text-transform: uppercase; letter-spacing: .08em; margin-top: 1px;
    }

    /* Collapse button */
    .b360-collapse-btn {
        width: 26px; height: 26px;
        border-radius: var(--sb-r-sm);
        border: 1px solid var(--sb-border);
        background: transparent;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; flex-shrink: 0;
        color: var(--sb-text-muted); font-size: 11px;
        transition: background .18s, color .18s, border-color .18s;
        margin-left: auto;
    }
    .b360-collapse-btn:hover {
        background: var(--sb-accent-soft);
        border-color: var(--sb-accent-200);
        color: var(--sb-accent);
    }
    body.sidebar-collapsed .b360-collapse-btn { margin: 0 auto; }

    /* Icon rotation — span wrapper avoids FA specificity clash */
    .b360-collapse-icon {
        display: inline-block;
        transition: transform .28s var(--sb-ease);
    }
    body.sidebar-collapsed .b360-collapse-icon { transform: rotate(180deg); }

    /* Search */
    .b360-sidebar-search {
        display: flex; align-items: center; gap: 7px;
        margin: 10px 12px 6px; padding: 7px 10px;
        background: #F7F9FC; border: 1px solid var(--sb-border);
        border-radius: var(--sb-r-md); flex-shrink: 0; overflow: hidden;
        transition: opacity .18s, height .28s var(--sb-ease),
                    margin .28s var(--sb-ease), padding .28s var(--sb-ease);
    }
    .b360-sidebar-search:focus-within {
        border-color: var(--sb-accent); background: var(--sb-bg);
        box-shadow: 0 0 0 3px rgba(37,99,235,.10);
    }
    .b360-sidebar-search i   { font-size: 12px; color: var(--sb-text-muted); flex-shrink: 0; }
    .b360-sidebar-search input {
        flex: 1; border: none; background: transparent;
        font-size: 13px; color: var(--sb-text); outline: none;
        font-family: var(--sb-font); min-width: 0;
    }
    .b360-sidebar-search input::placeholder { color: var(--sb-text-muted); }
    .b360-sidebar-search button {
        background: none; border: none; cursor: pointer;
        color: var(--sb-text-muted); font-size: 11px;
        padding: 0; transition: color .15s; flex-shrink: 0;
    }
    .b360-sidebar-search button:hover { color: var(--sb-accent); }
    body.sidebar-collapsed .b360-sidebar-search {
        opacity: 0; pointer-events: none;
        height: 0; margin-top: 0; margin-bottom: 0; padding-top: 0; padding-bottom: 0;
    }

    /* Nav scroll area */
    .b360-nav {
        flex: 1; overflow-y: auto; overflow-x: hidden;
        padding: 6px 8px 8px; -webkit-overflow-scrolling: touch;
    }
    .b360-nav::-webkit-scrollbar { width: 3px; }
    .b360-nav::-webkit-scrollbar-track { background: transparent; }
    .b360-nav::-webkit-scrollbar-thumb { background: var(--sb-border-2); border-radius: 3px; }
    .b360-nav::-webkit-scrollbar-thumb:hover { background: var(--sb-accent-200); }

    /* Nav group */
    .b360-nav-group  { margin-bottom: 4px; }
    .b360-nav-group-label {
        font-size: 9.5px; font-weight: 700; text-transform: uppercase;
        letter-spacing: .12em; color: var(--sb-text-muted);
        padding: 12px 10px 5px; white-space: nowrap; overflow: hidden;
        display: block; transition: opacity .18s;
    }
    body.sidebar-collapsed .b360-nav-group-label {
        opacity: 0; pointer-events: none; padding-top: 8px; padding-bottom: 2px;
    }

    /* Nav link */
    .b360-nav-link {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 10px; border-radius: var(--sb-r-md);
        color: var(--sb-text-3); text-decoration: none;
        font-size: 13.5px; font-weight: 500; margin: 1px 0;
        border: 1px solid transparent;
        transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap; overflow: hidden; min-height: 40px; position: relative;
    }
    .b360-nav-link:hover { background: var(--sb-bg-hover); color: var(--sb-text); text-decoration: none; }
    .b360-nav-link.is-active {
        background: var(--sb-accent-soft); color: var(--sb-accent);
        font-weight: 600; border-color: var(--sb-accent-200);
    }
    .b360-nav-link.is-disabled { opacity: .42; cursor: not-allowed; pointer-events: none; }
    body.sidebar-collapsed .b360-nav-link { justify-content: center; padding: 8px; gap: 0; }

    /* Nav icon */
    .b360-nav-icon {
        width: 32px; height: 32px; border-radius: var(--sb-r-sm);
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; color: var(--sb-text-3); flex-shrink: 0;
        transition: background .15s, color .15s;
    }
    .b360-nav-link:hover .b360-nav-icon,
    .b360-nav-link.is-active .b360-nav-icon { background: rgba(37,99,235,.10); color: var(--sb-accent); }

    /* Nav label */
    .b360-nav-label {
        flex: 1; min-width: 0; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap; transition: opacity .18s;
    }
    body.sidebar-collapsed .b360-nav-label { opacity: 0; width: 0; pointer-events: none; }

    /* Badge */
    .b360-nav-badge {
        margin-left: auto; background: var(--sb-danger); color: #fff;
        font-size: 10px; font-weight: 700; padding: 2px 6px;
        border-radius: 20px; flex-shrink: 0; white-space: nowrap; transition: opacity .18s;
    }
    body.sidebar-collapsed .b360-nav-badge {
        opacity: 0; width: 0; overflow: hidden; padding: 0; pointer-events: none;
    }

    /* Profile */
    .b360-profile-section {
        border-top: 1px solid var(--sb-border);
        padding: 8px 10px 12px; flex-shrink: 0; position: relative;
    }
    .b360-profile-summary {
        display: flex; align-items: center; gap: 10px; padding: 8px;
        border-radius: var(--sb-r-md); cursor: pointer; list-style: none;
        border: 1px solid transparent;
        transition: background .14s, border-color .14s;
        overflow: hidden; white-space: nowrap;
    }
    .b360-profile-summary::-webkit-details-marker { display: none; }
    .b360-profile-summary:hover { background: var(--sb-bg-hover); border-color: var(--sb-border); }

    .b360-avatar {
        width: 32px; height: 32px; border-radius: var(--sb-r-sm);
        background: linear-gradient(135deg, var(--sb-brand), #f3b582);
        color: #fff; font-size: 12px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; letter-spacing: .02em;
    }
    .b360-profile-copy { flex: 1; min-width: 0; overflow: hidden; transition: opacity .18s; }
    body.sidebar-collapsed .b360-profile-copy { opacity: 0; width: 0; pointer-events: none; }

    .b360-profile-name {
        font-size: 13px; font-weight: 600; color: var(--sb-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .b360-profile-role {
        font-size: 11px; color: var(--sb-text-muted);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px;
    }
    .b360-profile-chevron {
        font-size: 10px; color: var(--sb-text-muted); flex-shrink: 0;
        transition: transform .2s, opacity .18s;
    }
    .b360-profile-menu[open] .b360-profile-chevron { transform: rotate(180deg); }
    body.sidebar-collapsed .b360-profile-chevron  { opacity: 0; width: 0; pointer-events: none; }
    body.sidebar-collapsed .b360-profile-summary  { justify-content: center; padding: 6px; gap: 0; }

    .b360-profile-popover {
        position: absolute; bottom: calc(100% + 4px); left: 10px; right: 10px;
        background: var(--sb-bg); border: 1px solid var(--sb-border);
        border-radius: 14px; box-shadow: 0 8px 28px rgba(0,0,0,.10);
        overflow: hidden; z-index: 300; padding: 5px;
    }
    .b360-profile-popover a,
    .b360-profile-popover button {
        display: flex; align-items: center; gap: 9px; padding: 9px 12px;
        border-radius: var(--sb-r-sm); font-size: 13px; font-weight: 500;
        color: var(--sb-text-2); text-decoration: none; cursor: pointer;
        border: none; background: transparent; width: 100%;
        font-family: var(--sb-font); transition: background .13s, color .13s;
    }
    .b360-profile-popover a:hover,
    .b360-profile-popover button:hover { background: var(--sb-bg-hover); color: var(--sb-text); text-decoration: none; }
    .b360-profile-popover a i,
    .b360-profile-popover button i { font-size: 13px; color: var(--sb-text-muted); width: 16px; text-align: center; }
    .b360-profile-popover form:last-child button:hover { color: var(--sb-danger); }
    .b360-profile-popover form:last-child button:hover i { color: var(--sb-danger); }
    body.sidebar-collapsed .b360-profile-popover { display: none; }

    /* Tooltip (created by JS) */
    .b360-sb-tooltip {
        position: fixed;
        background: #1E293B; color: #F1F5F9;
        font-size: 12px; font-weight: 600;
        font-family: var(--sb-font);
        padding: 5px 10px; border-radius: var(--sb-r-sm);
        white-space: nowrap; pointer-events: none;
        z-index: 9999; box-shadow: 0 4px 14px rgba(0,0,0,.18);
        opacity: 0; transition: opacity .12s;
    }
    .b360-sb-tooltip.is-visible { opacity: 1; }

    /* Mobile */
    @media (max-width: 768px) {
        .b360-sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            transform: translateX(-100%);
            transition: transform .28s var(--sb-ease);
            box-shadow: 4px 0 28px rgba(0,0,0,.10);
            width: var(--sb-w-open) !important;
        }
        /* Mobile open state is driven by body.nav-open (Alpine sets this) */
        body.nav-open .b360-sidebar { transform: translateX(0); }
        /* Never apply icon-rail on mobile */
        body.sidebar-collapsed .b360-sidebar { width: var(--sb-w-open) !important; }
    }

    .visually-hidden {
        position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }
</style>


{{-- ───────────── SIDEBAR MARKUP ───────────── --}}

<aside
    class="b360-sidebar"
    x-bind:class="sidebarClasses"
    x-ref="sidebar"
    id="b360Sidebar"
    tabindex="-1"
    aria-label="Primary navigation"
>

    {{-- Brand --}}
    <div class="b360-brand">
        <a href="{{ route('builder360.dashboard') }}" class="b360-brand-logo-link" title="Builder365 ERP & CRM">
            <img src="https://build365.arinine.com/Logo1.png" alt="Builder365 ERP & CRM" class="b360-logo-full">
            <div class="b360-logo-compact" aria-hidden="true">
                <span>B365</span>
            </div>
        </a>
       
        <button
            class="b360-collapse-btn"
            id="b360CollapseBtn"
            type="button"
            x-on:click="toggleSidebar"
            x-bind:aria-label="navigationLabel"
            x-bind:title="navigationLabel"
            x-bind:aria-expanded="navigationExpanded"
        >
            <span class="b360-collapse-icon" aria-hidden="true">
                <i class="fa-solid fa-chevron-left"></i>
            </span>
        </button>
    </div>

    {{-- Search --}}
    <form
        class="b360-sidebar-search"
        method="GET"
        action="{{ route('builder360.search') }}"
        role="search"
    >
        <label class="visually-hidden" for="b360-sidebar-global-search">Search Builder360</label>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input
            id="b360-sidebar-global-search"
            name="q"
            type="search"
            value="{{ request()->routeIs('builder360.search') ? request('q') : '' }}"
            minlength="2" maxlength="100"
            placeholder="Search..."
            autocomplete="off"
            required
        >
        <button type="submit" aria-label="Submit search">
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </button>
    </form>

    <nav class="b360-nav" aria-label="Main navigation">
        @php
            $allowedItems = [
                'Task Management',
                'Calendar Management',
                'Chat Connect',
                'Mailbox',
            ];

            $isDirector = auth()->user()->role?->slug === 'directords';
        @endphp
    
        @forelse ($shell->navigation as $group)
    
            @php
                $visibleItems = collect($group->items)->filter(
                    fn($item) => in_array($item->name, $allowedItems, true)
                );
                $isSystemGroup = strtolower($group->label) === 'system';
    
                if ($isSystemGroup && ! $isDirector) continue;
    
                if (! $isSystemGroup && $visibleItems->isEmpty()) continue;
            @endphp
    
            <section class="b360-nav-group" aria-label="{{ $group->label }}">
                <span class="b360-nav-group-label" aria-hidden="true">{{ $group->label }}</span>
    
                @foreach ($group->items as $item)
    
                    @php
                        if (! $isSystemGroup && ! in_array($item->name, $allowedItems, true)) continue;
                    @endphp
    
                    @if ($item->url)
                        <a
                            href="{{ $item->url }}"
                            class="b360-nav-link {{ $item->active ? 'is-active' : '' }}"
                            @if ($item->active) aria-current="page" @endif
                            data-label="{{ $item->name }}"
                        >
                            <span class="b360-nav-icon" aria-hidden="true">
                                <i class="fa-solid {{ $item->iconClass }}"></i>
                            </span>
                            <span class="b360-nav-label">{{ $item->name }}</span>
                            @if (!empty($item->badge) && $item->badge > 0)
                                <span class="b360-nav-badge" aria-label="{{ $item->badge }} notifications">{{ $item->badge }}</span>
                            @elseif (!empty($item->unreadCount) && $item->unreadCount > 0)
                                <span class="b360-nav-badge" aria-label="{{ $item->unreadCount }} unread">{{ $item->unreadCount }}</span>
                            @endif
                        </a>
                    @else
                        <span
                            class="b360-nav-link is-disabled"
                            aria-disabled="true"
                            data-label="{{ $item->name }}"
                            role="link"
                        >
                            <span class="b360-nav-icon" aria-hidden="true">
                                <i class="fa-solid {{ $item->iconClass }}"></i>
                            </span>
                            <span class="b360-nav-label">{{ $item->name }}</span>
                        </span>
                    @endif
    
                @endforeach
            </section>
    
        @empty
            <p style="padding:16px;font-size:13px;color:var(--sb-text-muted)">
                No authorized modules are available.
            </p>
        @endforelse
    </nav>
   

    <details class="b360-profile-section b360-profile-menu">
        <summary class="b360-profile-summary">
            <div class="b360-avatar" aria-hidden="true">{{ $shell->userInitial }}</div>
            <div class="b360-profile-copy">
                <div class="b360-profile-name">{{ $shell->userName }}</div>
                <div class="b360-profile-role">{{ $shell->activeRoleName }}</div>
            </div>
            <i class="fa-solid fa-chevron-up b360-profile-chevron" aria-hidden="true"></i>
        </summary>
        <nav class="b360-profile-popover" aria-label="Profile options">
            <!-- 
                <a href="{{ route('builder360.dashboard') }}">
                    <i class="fa-solid fa-gauge" aria-hidden="true"></i> My Dashboard
                </a> -->
            <a href="{{ route('builder360.profile') }}">
                <i class="fa-regular fa-user" aria-hidden="true"></i> My Profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Logout
                </button>
            </form>
        </nav>
    </details>

</aside>

{{-- Retired legacy sidebar script. The CSP-safe builderShell in resources/js/app.js is authoritative.
@push('scripts')

<script>
(function () {
    var sidebar = document.getElementById('b360Sidebar');
    if (!sidebar) return;

    var tooltip = document.createElement('div');
    tooltip.className = 'b360-sb-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    document.body.appendChild(tooltip);

    sidebar.querySelectorAll('.b360-nav-link').forEach(function (link) {
        link.addEventListener('mouseenter', function () {
            if (!document.body.classList.contains('sidebar-collapsed')) return;
            var label = link.getAttribute('data-label');
            if (!label) return;
            var r = link.getBoundingClientRect();
            tooltip.textContent     = label;
            tooltip.style.top       = Math.round(r.top + r.height / 2) + 'px';
            tooltip.style.left      = Math.round(r.right + 10) + 'px';
            tooltip.style.transform = 'translateY(-50%)';
            tooltip.classList.add('is-visible');
        });
        link.addEventListener('mouseleave', function () {
            tooltip.classList.remove('is-visible');
        });
    });

    /* Hide tooltip when sidebar expands */
    new MutationObserver(function () {
        if (!document.body.classList.contains('sidebar-collapsed')) {
            tooltip.classList.remove('is-visible');
        }
    }).observe(document.body, { attributes: true, attributeFilter: ['class'] });
})();
function retiredSidebarController() {
    return {

        /* ── State ── */
        sidebarCollapsed: false,   // desktop icon-only rail
        navOpen:          false,   // mobile slide-over

        /* ── Lifecycle ── */
        init() {
            // Restore persisted state (defaults to collapsed unless explicitly uncollapsed)
            const storedState = this._storageGet(SIDEBAR_KEY);
            this.sidebarCollapsed = storedState === null || storedState === undefined ? true : storedState === '1';

            // Apply immediately without a transition so there's no flash on load.
            const sidebar = document.getElementById('b360Sidebar');
            if (sidebar) {
                sidebar.style.transition = 'none';
                this.$nextTick(() => {
                    void sidebar.offsetWidth; // force reflow
                    sidebar.style.transition = '';
                });
            }
        },

        /* ── Computed classes bound to <body> via x-bind:class ── */
        get navigationClasses() {
            return {
                'nav-open':          this.navOpen,
                'sidebar-collapsed': this.sidebarCollapsed,
            };
        },

        /* ── Public actions ── */

        /**
         * Topbar hamburger click handler.
         * Use:  x-on:click="handleMenuToggle()"
         *
         * DO NOT use `window.innerWidth` directly in Alpine expressions —
         * Alpine 3 evaluates x-on:click in a scope where `window` is undefined.
         * Delegating to a method is the correct pattern.
         */
        handleMenuToggle() {
            if (this._isMobile()) {
                this.toggleNavigation();
            } else {
                this.toggleSidebar();
            }
        },

        /** Mobile: open/close the slide-over overlay. */
        toggleNavigation() {
            this.navOpen = !this.navOpen;
        },

        /** Close mobile overlay (Escape key, backdrop click). */
        closeNavigation() {
            this.navOpen = false;
        },

        /**
         * Called on window resize via x-on:resize.window="closeNavigationOnDesktop".
         * Closes the mobile overlay when the viewport grows past 768 px.
         */
        closeNavigationOnDesktop() {
            if (!this._isMobile()) {
                this.navOpen = false;
            }
        },

        /**
         * Desktop: toggle sidebar between full-width and icon-rail.
         * State is stored on body.sidebar-collapsed; the sidebar CSS
         * reads it from there — no direct DOM manipulation needed.
         */
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            this._storageSet(SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0');
            this._syncCollapseButton();
        },

        /* ── Private helpers ── */

        _isMobile() {
            return window.innerWidth <= 768;
        },

        _storageGet(key) {
            try { return localStorage.getItem(key); } catch (e) { return null; }
        },

        _storageSet(key, val) {
            try { localStorage.setItem(key, val); } catch (e) { /* ignore */ }
        },

        /** Keep the sidebar chevron button ARIA label in sync. */
        _syncCollapseButton() {
            const btn = document.getElementById('b360CollapseBtn');
            if (!btn) return;
            const label = this.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
        },
    };
}
</script>
@endpush
--}}
