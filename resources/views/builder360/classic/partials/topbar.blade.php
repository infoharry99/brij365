@push('styles')
<style>
    /* ══════════════════════════════════════════════════════
    DESIGN TOKENS
    ══════════════════════════════════════════════════════ */
    :root {
    --sb-bg:           #FFFFFF;
    --sb-bg-hover:     #F0F4FA;
    --sb-border:       #E2E8F2;
    --sb-border-2:     #C7D5EA;
    --sb-accent:       #F6740C;
    --sb-accent-soft:  #EEF4FF;
    --sb-accent-100:   #DBEAFE;
    --sb-accent-200:   #BFDBFE;
    --sb-text:         #0F172A;
    --sb-text-2:       #334155;
    --sb-text-3:       #64748B;
    --sb-text-muted:   #94A3B8;
    --sb-success:      #10B981;
    --sb-danger:       #EF4444;
    --sb-brand:        #F6740C;
    --sb-w-open:       260px;
    --sb-w-closed:     64px;
    --tb-h:            56px;
    --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
            'Helvetica Neue', Arial, sans-serif;
    --ease: cubic-bezier(0.16, 1, 0.3, 1);
    --r-sm: 8px; --r-md: 10px; --r-lg: 14px;
    }

    /* ══════════════════════════════════════════════════════
    LAYOUT SHELL
    ══════════════════════════════════════════════════════ */
    .b360-shell {
    display: flex;
    height: 100vh;
    overflow: hidden;
    font-family: var(--font);
    -webkit-font-smoothing: antialiased;
    background: #F0F4FA;
    }

    /* ══════════════════════════════════════════════════════
    SIDEBAR
    ══════════════════════════════════════════════════════ */
    .b360-sidebar {
    width: var(--sb-w-open);
    flex-shrink: 0;
    background: var(--sb-bg);
    border-right: 1px solid var(--sb-border);
    display: flex;
    flex-direction: column;
    position: relative;
    z-index: 200;
    transition: width .28s var(--ease);
    overflow: hidden;
    }

    /* Collapsed state */
    .b360-sidebar.is-collapsed {
    width: var(--sb-w-closed);
    }

    /* Top blue accent line */
    .b360-sidebar::before {
    content: '';
    display: block;
    height: 3px;
    flex-shrink: 0;
    background: linear-gradient(90deg, var(--sb-brand), #818cf8);
    }

    /* ── Brand / Logo ── */
    .b360-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 14px 12px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--sb-border);
    min-height: 60px;
    overflow: hidden;
    white-space: nowrap;
    }

    .b360-brand-icon {
    width: 36px; height: 36px;
    border-radius: var(--r-md);
    background: linear-gradient(135deg, var(--sb-brand), #818cf8);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #fff; font-size: 16px;
    box-shadow: 0 4px 12px rgba(79,70,229,.30);
    }

    .b360-brand-text { flex: 1; min-width: 0; overflow: hidden; transition: opacity .2s; }
    .b360-brand-title {
    font-size: 15px; font-weight: 700; color: var(--sb-text);
    white-space: nowrap; overflow: hidden;
    }
    .b360-brand-subtitle {
    font-size: 10.5px; font-weight: 600; color: var(--sb-text-muted);
    text-transform: uppercase; letter-spacing: .08em;
    white-space: nowrap; overflow: hidden;
    }

    /* ─────────────────────────────────────────────
       FIX: Collapse/expand toggle — stays visible
    ───────────────────────────────────────────── */
    .b360-collapse-btn {
    width: 28px; height: 28px;
    border-radius: var(--r-sm);
    border: 1px solid var(--sb-border);
    background: var(--sb-bg);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
    color: var(--sb-text-3); font-size: 12px;
    transition: all .18s, transform .28s var(--ease);
    margin-left: auto;
    }
    .b360-collapse-btn:hover {
    background: var(--sb-accent-soft);
    border-color: var(--sb-accent-200);
    color: var(--sb-accent);
    }
    /* When collapsed: re-centre button and rotate chevron */
    .b360-sidebar.is-collapsed .b360-collapse-btn {
    margin: 0 auto;
    opacity: 1   !important;
    width: 28px  !important;
    pointer-events: auto !important;
    }
    .b360-sidebar.is-collapsed .b360-collapse-btn i {
    transform: rotate(180deg);
    }

    /* When collapsed, hide text elements */
    .b360-sidebar.is-collapsed .b360-brand-text,
    .b360-sidebar.is-collapsed .b360-sidebar-search,
    .b360-sidebar.is-collapsed .b360-nav-group h2,
    .b360-sidebar.is-collapsed .b360-nav-label,
    .b360-sidebar.is-collapsed .b360-profile-copy,
    .b360-sidebar.is-collapsed .b360-profile-chevron,
    .b360-sidebar.is-collapsed .b360-nav-badge {
    opacity: 0;
    pointer-events: none;
    width: 0;
    overflow: hidden;
    }
    .b360-sidebar.is-collapsed .b360-brand-icon { margin: 0 auto; }

    /* ── Sidebar search ── */
    .b360-sidebar-search {
    display: flex; align-items: center; gap: 7px;
    margin: 10px 12px 6px;
    padding: 7px 10px;
    background: #F7F9FC;
    border: 1px solid var(--sb-border);
    border-radius: var(--r-md);
    flex-shrink: 0;
    transition: all .18s;
    overflow: hidden;
    white-space: nowrap;
    }
    .b360-sidebar-search:focus-within {
    border-color: var(--sb-accent);
    background: var(--sb-bg);
    box-shadow: 0 0 0 3px rgba(37,99,235,.10);
    }
    .b360-sidebar-search i { font-size: 12px; color: var(--sb-text-muted); flex-shrink: 0; }
    .b360-sidebar-search input {
    flex: 1; border: none; background: transparent;
    font-size: 13px; color: var(--sb-text); outline: none;
    font-family: var(--font); min-width: 0;
    }
    .b360-sidebar-search input::placeholder { color: var(--sb-text-muted); }
    .b360-sidebar-search button {
    background: none; border: none; cursor: pointer;
    color: var(--sb-text-muted); font-size: 11px; padding: 0;
    transition: color .15s; flex-shrink: 0;
    }
    .b360-sidebar-search button:hover { color: var(--sb-accent); }

    /* ── Nav (scrollable) ── */
    .b360-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 6px 8px 10px;
    }
    .b360-nav::-webkit-scrollbar { width: 4px; }
    .b360-nav::-webkit-scrollbar-track { background: transparent; }
    .b360-nav::-webkit-scrollbar-thumb { background: var(--sb-border-2); border-radius: 4px; }
    .b360-nav::-webkit-scrollbar-thumb:hover { background: var(--sb-accent-200); }

    /* Nav group */
    .b360-nav-group { margin-bottom: 6px; }
    .b360-nav-group h2 {
    font-size: 9.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .12em;
    color: var(--sb-text-muted);
    padding: 12px 10px 5px;
    white-space: nowrap; overflow: hidden;
    transition: opacity .2s;
    }

    /* Nav link */
    .b360-nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px;
    border-radius: var(--r-md);
    color: var(--sb-text-3);
    text-decoration: none;
    font-size: 13.5px; font-weight: 500;
    margin: 1px 0;
    border: 1px solid transparent;
    transition: all .16s;
    position: relative;
    white-space: nowrap;
    overflow: hidden;
    }
    .b360-nav-link:hover {
    background: var(--sb-bg-hover);
    color: var(--sb-text);
    text-decoration: none;
    }
    .b360-nav-link.is-active {
    background: var(--sb-accent-soft);
    color: var(--sb-accent);
    font-weight: 600;
    border-color: var(--sb-accent-200);
    }
    .b360-nav-link.is-active .b360-nav-icon { color: var(--sb-accent); }
    .b360-nav-link.is-disabled {
    opacity: .45; cursor: not-allowed;
    }

    /* Nav icon */
    .b360-nav-icon {
    width: 32px; height: 32px;
    border-radius: var(--r-sm);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--sb-text-3);
    flex-shrink: 0;
    background: transparent;
    transition: background .15s, color .15s;
    }
    .b360-nav-link:hover .b360-nav-icon {
    background: rgba(37,99,235,.07);
    color: var(--sb-accent);
    }
    .b360-nav-link.is-active .b360-nav-icon {
    background: rgba(37,99,235,.12);
    color: var(--sb-accent);
    }

    /* Nav badge (count) */
    .b360-nav-badge {
    margin-left: auto;
    background: #EF4444;
    color: #fff;
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 20px;
    flex-shrink: 0;
    transition: opacity .2s, width .2s;
    white-space: nowrap;
    }

    /* Nav label (text span) */
    .b360-nav-label {
    flex: 1; min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    transition: opacity .2s;
    }

    /* ── Collapsed tooltips ── */
    .b360-sidebar.is-collapsed .b360-nav-link {
    justify-content: center;
    padding: 8px;
    }
    .b360-sidebar.is-collapsed .b360-nav-link:hover::after {
    content: attr(data-label);
    position: fixed;
    left: calc(var(--sb-w-closed) + 12px);
    top: 50%; transform: translateY(-50%);
    background: #1E293B;
    color: #F1F5F9;
    font-size: 12px; font-weight: 600;
    padding: 5px 10px;
    border-radius: var(--r-sm);
    white-space: nowrap;
    pointer-events: none;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
    }
    .b360-sidebar.is-collapsed .b360-nav-link:hover::before {
    content: '';
    position: fixed;
    left: calc(var(--sb-w-closed) + 4px);
    top: 50%; transform: translateY(-50%);
    border: 5px solid transparent;
    border-right-color: #1E293B;
    pointer-events: none;
    z-index: 9999;
    }

    /* ── Profile section ── */
    .b360-profile-section {
    border-top: 1px solid var(--sb-border);
    padding: 10px 10px 12px;
    flex-shrink: 0;
    position: relative;
    }

    .b360-profile-summary {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 8px;
    border-radius: var(--r-md);
    cursor: pointer; list-style: none;
    border: 1px solid transparent;
    transition: all .15s;
    white-space: nowrap; overflow: hidden;
    }
    .b360-profile-summary::-webkit-details-marker { display: none; }
    .b360-profile-summary:hover {
    background: var(--sb-bg-hover);
    border-color: var(--sb-border);
    }

    .b360-avatar {
    width: 32px; height: 32px;
    border-radius: var(--r-sm);
    background: linear-gradient(135deg, var(--sb-brand), #818cf8);
    color: #fff;
    font-size: 12px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    letter-spacing: .02em;
    }

    .b360-profile-copy { flex: 1; min-width: 0; overflow: hidden; transition: opacity .2s; }
    .b360-profile-name {
    font-size: 13px; font-weight: 600; color: var(--sb-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .b360-profile-role {
    font-size: 11px; color: var(--sb-text-muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 1px;
    }
    .b360-profile-chevron {
    font-size: 10px; color: var(--sb-text-muted);
    transition: transform .2s, opacity .2s;
    flex-shrink: 0;
    }
    .b360-profile-menu[open] .b360-profile-chevron { transform: rotate(180deg); }

    /* Profile popover */
    .b360-profile-popover {
    position: absolute;
    bottom: calc(100% + 4px);
    left: 10px; right: 10px;
    background: var(--sb-bg);
    border: 1px solid var(--sb-border);
    border-radius: var(--r-lg);
    box-shadow: 0 8px 24px rgba(0,0,0,.10);
    overflow: hidden;
    z-index: 300;
    padding: 4px;
    }
    .b360-profile-popover a,
    .b360-profile-popover button {
    display: flex; align-items: center; gap: 9px;
    padding: 9px 12px;
    border-radius: var(--r-md);
    font-size: 13px; font-weight: 500; color: var(--sb-text-2);
    text-decoration: none; cursor: pointer;
    border: none; background: transparent;
    width: 100%; font-family: var(--font);
    transition: background .13s, color .13s;
    }
    .b360-profile-popover a:hover,
    .b360-profile-popover button:hover {
    background: var(--sb-bg-hover); color: var(--sb-text); text-decoration: none;
    }
    .b360-profile-popover a i,
    .b360-profile-popover button i { font-size: 13px; color: var(--sb-text-muted); width: 16px; text-align: center; }
    .b360-profile-popover button:last-child:hover { color: var(--sb-danger); }
    .b360-profile-popover button:last-child:hover i { color: var(--sb-danger); }

    /* Collapsed profile — just show avatar centred */
    .b360-sidebar.is-collapsed .b360-profile-summary {
    justify-content: center; padding: 6px;
    }
    .b360-sidebar.is-collapsed .b360-profile-popover { display: none; }

    /* ══════════════════════════════════════════════════════
    TOPBAR
    ══════════════════════════════════════════════════════ */
    .b360-topbar {
    height: var(--tb-h);
    background: var(--sb-bg);
    border-bottom: 1px solid var(--sb-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px 0 16px;
    gap: 12px;
    position: sticky;
    top: 0; z-index: 100;
    box-shadow: 0 1px 4px rgba(0,0,0,.04);
    }

    /* Leading side */
    .b360-topbar-leading {
    display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;
    }

    /* ─────────────────────────────────────────────
       FIX: Menu toggle button
       On desktop (>768px): toggles sidebar collapse
       On mobile  (≤768px): opens sidebar overlay
    ───────────────────────────────────────────── */
    .b360-menu-toggle {
    width: 34px; height: 34px;
    border: 1px solid var(--sb-border);
    border-radius: var(--r-sm);
    background: transparent; color: var(--sb-text-3);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 15px; flex-shrink: 0;
    transition: all .15s;
    }
    .b360-menu-toggle:hover {
    background: var(--sb-accent-soft); border-color: var(--sb-accent-200); color: var(--sb-accent);
    }

    /* Topbar search */
    .b360-topbar-search {
    display: flex; align-items: center; gap: 8px;
    background: #F7F9FC;
    border: 1px solid var(--sb-border);
    border-radius: var(--r-md);
    padding: 7px 12px;
    max-width: 440px; flex: 1;
    transition: all .18s;
    }
    .b360-topbar-search:focus-within {
    border-color: var(--sb-accent);
    background: var(--sb-bg);
    box-shadow: 0 0 0 3px rgba(37,99,235,.10);
    }
    .b360-topbar-search i { font-size: 12px; color: var(--sb-text-muted); flex-shrink: 0; }
    .b360-topbar-search input {
    flex: 1; border: none; background: transparent;
    font-size: 13px; color: var(--sb-text); outline: none; font-family: var(--font);
    }
    .b360-topbar-search input::placeholder { color: var(--sb-text-muted); }
    .b360-topbar-search button {
    background: none; border: none; cursor: pointer;
    color: var(--sb-text-muted); font-size: 11px; padding: 0;
    transition: color .15s; flex-shrink: 0;
    }
    .b360-topbar-search button:hover { color: var(--sb-accent); }

    /* Actions */
    .b360-topbar-actions {
    display: flex; align-items: center; gap: 6px; flex-shrink: 0;
    }

    /* Context form (project/role selector) */
    .b360-context-form {
    display: flex; align-items: center; gap: 7px;
    padding: 5px 12px;
    border: 1px solid var(--sb-border);
    border-radius: 20px;
    background: var(--sb-bg);
    font-size: 13px; font-weight: 500;
    color: var(--sb-text-2); cursor: pointer;
    transition: all .15s;
    }
    .b360-context-form:hover { border-color: var(--sb-accent-200); background: var(--sb-accent-soft); color: var(--sb-accent); }
    .b360-context-form select {
    border: none; background: transparent; outline: none;
    font-size: 13px; font-weight: 600; color: inherit;
    cursor: pointer; font-family: var(--font); max-width: 120px;
    }

    /* Status dot */
    .b360-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--sb-success); flex-shrink: 0;
    box-shadow: 0 0 0 2px rgba(16,185,129,.2);
    }

    /* Pill (read-only project label) */
    .b360-pill {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 5px 12px;
    border: 1px solid var(--sb-border);
    border-radius: 20px;
    font-size: 13px; font-weight: 600; color: var(--sb-text-2);
    }

    /* Role chip (avatar + name) */
    .b360-role-chip {
    display: flex; align-items: center; gap: 8px;
    padding: 4px 12px 4px 5px;
    border: 1px solid var(--sb-border);
    border-radius: 20px;
    background: var(--sb-bg);
    font-size: 13px; font-weight: 600; color: var(--sb-text-2);
    }
    .b360-role-chip strong { display: block; font-size: 12.5px; font-weight: 600; line-height: 1.2; }
    .b360-role-chip small  { display: block; font-size: 10.5px; color: var(--sb-text-muted); line-height: 1; }

    /* Avatar small */
    .b360-avatar-sm {
    width: 26px; height: 26px;
    border-radius: 7px;
    font-size: 10px;
    }

    /* Icon button */
    .b360-icon-btn {
    width: 34px; height: 34px;
    border: 1px solid var(--sb-border);
    border-radius: var(--r-sm);
    background: transparent; color: var(--sb-text-3);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; position: relative;
    text-decoration: none;
    transition: all .15s;
    }
    .b360-icon-btn:hover {
    background: var(--sb-accent-soft); border-color: var(--sb-accent-200); color: var(--sb-accent);
    }

    /* Notification count badge */
    .b360-count {
    position: absolute;
    top: -4px; right: -4px;
    min-width: 16px; height: 16px;
    background: var(--sb-danger);
    color: #fff;
    font-size: 9px; font-weight: 700;
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 4px;
    border: 2px solid var(--sb-bg);
    }

    /* ══════════════════════════════════════════════════════
    MAIN CONTENT AREA
    ══════════════════════════════════════════════════════ */
    .b360-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    }
    .b360-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    }
    .b360-content::-webkit-scrollbar { width: 5px; }
    .b360-content::-webkit-scrollbar-track { background: transparent; }
    .b360-content::-webkit-scrollbar-thumb { background: #C7D5EA; border-radius: 4px; }

    /* ══════════════════════════════════════════════════════
    RESPONSIVE
    ══════════════════════════════════════════════════════ */
    @media (max-width: 768px) {
    .b360-sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        transform: translateX(-100%);
        transition: transform .28s var(--ease), width .28s var(--ease);
        box-shadow: 4px 0 24px rgba(0,0,0,.10);
    }
    .b360-sidebar.is-open {
        transform: translateX(0);
        width: var(--sb-w-open) !important;
    }
    .b360-sidebar-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0,0,0,.35);
        z-index: 199;
    }
    .b360-sidebar.is-open ~ .b360-sidebar-overlay { display: block; }
    }

    /* Visually hidden (accessibility) */
    .visually-hidden {
    position: absolute; width: 1px; height: 1px;
    padding: 0; margin: -1px; overflow: hidden;
    clip: rect(0,0,0,0); white-space: nowrap; border: 0;
    }
    </style>
@endpush


{{-- Mobile overlay --}}
{{-- ══════════════════════════════════════════════════════════════
     TOPBAR
     ══════════════════════════════════════════════════════════════ --}}
<header class="b360-topbar">
  <div class="b360-topbar-leading">

    {{--
        FIX: The hamburger now handles both cases:
        - Mobile  (≤768px): calls toggleNavigation() to slide the sidebar in
        - Desktop (>768px): calls toggleSidebar()    to collapse/expand
        We use a simple inline x-on:click that checks window.innerWidth.
    --}}
    <button
      class="b360-menu-toggle b360-icon-btn"
      type="button"
      x-ref="menuToggle"
      x-on:click="handleMenuToggle"
      aria-controls="b360Sidebar"
      x-bind:aria-expanded="navigationExpanded"
      x-bind:aria-label="navigationLabel"
      x-bind:title="navigationLabel"
    >
      <span aria-hidden="true">☰</span>
    </button>

    {{-- Global search --}}
    <form
      class="b360-topbar-search"
      method="GET"
      action="{{ route('builder360.search') }}"
      role="search"
    >
      <label class="visually-hidden" for="b360-global-search">
        Search projects, units, leads, and vouchers
      </label>
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input
        id="b360-global-search"
        name="q"
        type="search"
        value="{{ request()->routeIs('builder360.search') ? request('q') : '' }}"
        minlength="2" maxlength="100"
        placeholder="Search projects, units, leads, vouchers"
        autocomplete="off"
        required
      >
      <button type="submit" aria-label="Search">
        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
      </button>
    </form>

  </div>

  <div class="b360-topbar-actions">

    {{-- Project context switcher --}}
    <!-- @if ($shell->canSwitchProjects && count($shell->projects) > 0)
      <form
        method="POST"
        action="{{ route('builder360.project-context.store') }}"
        class="b360-context-form"
        x-data="autoSubmitForm"
      >
        @csrf
        <span class="b360-dot"></span>
        <label class="visually-hidden" for="b360-project-context">Project view</label>
        <select id="b360-project-context" name="project_id" aria-label="Project view" x-on:change="submitContext">
          <option value="all" @selected($shell->activeProjectId === null)>All Projects</option>
          @foreach ($shell->projects as $projectOption)
            <option value="{{ $projectOption->id }}" @selected($shell->activeProjectId === $projectOption->id)>
              {{ $projectOption->code }}
            </option>
          @endforeach
        </select>
        <noscript><button type="submit">Apply</button></noscript>
      </form>
    @else
      <span class="b360-pill">
        <span class="b360-dot"></span>
        {{ $shell->activeProjectLabel }}
      </span>
    @endif -->

    {{-- Role context switcher --}}
    @if ($shell->canSwitchRoles && count($shell->roles) > 1)
      <form
        method="POST"
        action="{{ route('builder360.role-context.store') }}"
        class="b360-context-form b360-role-context-form"
        x-data="autoSubmitForm"
      >
        @csrf
        <span class="b360-avatar b360-avatar-sm">{{ $shell->userInitial }}</span>
        <label class="visually-hidden" for="b360-role-context">View as role</label>
        <select id="b360-role-context" name="role_slug" aria-label="View as role" x-on:change="submitContext">
          @foreach ($shell->roles as $roleOption)
            <option value="{{ $roleOption->slug }}" @selected($shell->activeRoleSlug === $roleOption->slug)>
              {{ $roleOption->name }}
            </option>
          @endforeach
        </select>
        <noscript><button type="submit">Switch</button></noscript>
      </form>
    @else
      <span class="b360-role-chip">
        <span class="b360-avatar b360-avatar-sm">{{ $shell->userInitial }}</span>
        <span>
          <strong>{{ $shell->activeRoleName }}</strong>
          <small>Your role</small>
        </span>
      </span>
    @endif

    {{-- Theme toggle --}}
    <!-- <form method="POST" action="{{ route('builder360.theme.store') }}" x-on:submit="changeTheme">
      @csrf
      <input type="hidden" name="theme" value="{{ $shell->theme === 'dark' ? 'light' : 'dark' }}">
      <button
        class="b360-icon-btn"
        type="submit"
        x-bind:disabled="themeBusy"
        aria-label="Switch to {{ $shell->theme === 'dark' ? 'light' : 'dark' }} theme"
        x-bind:aria-label="themeLabel"
        x-bind:title="themeLabel"
      >
        <i class="fa-regular fa-moon" x-show="theme === 'light'" aria-hidden="true"></i>
        <i class="fa-regular fa-sun" x-show="theme === 'dark'" aria-hidden="true"></i>
      </button>
      <span class="b360-sr-only" aria-live="polite" x-text="themeError"></span>
    </form> -->

    {{-- Notifications --}}
    <!-- <a class="b360-icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications">
      <i class="fa-regular fa-bell"></i>
      @if ($shell->unreadNotifications > 0)
        <span class="b360-count">{{ $shell->unreadNotifications }}</span>
      @endif
    </a> -->

    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button class="b360-icon-btn" type="submit" aria-label="Logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i>
      </button>
    </form>

  </div>
</header>
