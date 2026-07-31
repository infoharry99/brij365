@extends('layouts.builder360-classic')

@section('title', 'Task Management | Builder360')

@php
    $scope = $filters['scope'] ?? 'dashboard';
    $view = $filters['view'] ?? 'board';
    $taskQuery = function (array $changes = []) use ($filters) {
        return array_filter(array_merge($filters, $changes), static fn ($value) => $value !== null && $value !== '');
    };
    $scopeLabels = [
        'dashboard' => 'Task Dashboard', 'mine' => 'My Tasks', 'assigned-to-me' => 'Assigned to Me',
        'assigned-by-me' => 'Assigned by Me', 'team' => 'Team Tasks', 'department' => 'Department Tasks',
        'all' => 'All Tasks', 'due-today' => 'Due Today', 'due-week' => 'Due This Week',
        'overdue' => 'Overdue Tasks', 'pending' => 'Pending Tasks', 'completed' => 'Completed Tasks',
        'archived' => 'Archived Tasks', 'activity' => 'Activity Center', 'reports' => 'Reports',
        'analytics' => 'Analytics', 'templates' => 'Templates', 'settings' => 'Settings',
    ];
@endphp
<style>

  /* ═══════════════════════════════════════════════════
    DESIGN TOKENS
    ═══════════════════════════════════════════════════ */
  :root {
    /* Surfaces */
    --tm-bg:          #F0F4FA;
    --tm-surface:     #F7F9FC;
    --tm-panel:       #FFFFFF;
    --tm-border:      #E2E8F2;
    --tm-border-2:    #C7D5EA;

    /* Blue accent */
    --tm-accent:      #F5852B;
    --tm-accent-l:    #EEF4FF;
    --tm-accent-100:  #DBEAFE;
    --tm-accent-200:  #BFDBFE;
    --tm-accent-700:  #1D4ED8;

    /* Text */
    --tm-text:        #0F172A;
    --tm-text-2:      #334155;
    --tm-sub:         #475569;
    --tm-muted:       #94A3B8;

    /* Status tones */
    --tm-green:       #059669;
    --tm-green-l:     #D1FAE5;
    --tm-violet:      #7C3AED;
    --tm-violet-l:    #EDE9FE;
    --tm-orange:      #D97706;
    --tm-orange-l:    #FEF3C7;
    --tm-red:         #DC2626;
    --tm-red-l:       #FEE2E2;
    --tm-indigo:      #4F46E5;
    --tm-slate:       #64748B;

    /* Priority colours */
    --tm-low:         #059669;
    --tm-medium:      #F5852B;
    --tm-high:        #D97706;
    --tm-urgent:      #DC2626;
    --tm-critical:    #7C3AED;

    /* Layout */
    --tm-rail-w:      240px;
    --tm-rail-w-sm:   56px;
    --tm-bar-h:       52px;

    /* Misc */
    --tm-shadow:      0 1px 3px rgba(15,23,42,.07), 0 1px 2px rgba(15,23,42,.05);
    --tm-shadow-md:   0 4px 16px rgba(15,23,42,.09);
    --tm-shadow-lg:   0 8px 28px rgba(15,23,42,.13);
    --tm-r-sm:        8px;
    --tm-r-md:        10px;
    --tm-r-lg:        14px;
    --tm-ease:        .15s ease;
    --tm-font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                      'Helvetica Neue', Arial, sans-serif;
  }

  /* ═══════════════════════════════════════════════════
    WORKSPACE SHELL
    ═══════════════════════════════════════════════════ */
  .b360-task-workspace {
    display: flex;
    height: 100vh;
    overflow: hidden;
    background: var(--tm-bg);
    font-family: var(--tm-font);
    font-size: 14px;
    color: var(--tm-text);
    -webkit-font-smoothing: antialiased;
    position: relative;
  }
  .b360-task-workspace * { box-sizing: border-box; }
  .b360-task-workspace.tm-fullscreen { position: fixed; inset: 0; z-index: 1000; }

  /* Live-update bar */
  .tm-live-update {
    position: fixed; top: 0; left: 0; right: 0; z-index: 500;
    background: #FEF9C3; border-bottom: 1px solid #FDE68A;
    padding: 8px 16px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    font-size: 14px; font-weight: 500; color: #92400E;
  }

  /* Alerts */
  .tm-workspace-alert { flex-shrink: 0; border-radius: 0; border-left: none; border-right: none; }
  .blade-alert { padding: 10px 16px; font-size: 14px; }
  .blade-alert-success { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
  .blade-alert-danger  { background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; }
  .blade-alert ul { margin: 4px 0 0 16px; }

  /* ═══════════════════════════════════════════════════
    LEFT RAIL
    ═══════════════════════════════════════════════════ */
  .tm-rail {
    width: var(--tm-rail-w);
    flex-shrink: 0;
    height: 100vh;
    background: var(--tm-panel);
    border-right: 1px solid var(--tm-border);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width .25s ease;
    position: relative;
    z-index: 100;
  }
  .tm-rail::before {
    content: '';
    display: block;
    height: 3px;
    flex-shrink: 0;
    background: linear-gradient(90deg, var(--tm-accent), #818cf8);
  }
  .tm-rail.workspace-hidden { display: none; }

  /* Rail head (New task button) */
  .tm-rail-head {
    padding: 14px 12px 10px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--tm-border);
  }

  /* New task button */
  .tm-new {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 16px;
    background: var(--tm-accent); color: #fff;
    border: none; border-radius: var(--tm-r-md);
    font-size: 14px; font-weight: 600;
    cursor: pointer; width: 100%; justify-content: center;
    font-family: var(--tm-font);
    box-shadow: 0 2px 8px rgba(37,99,235,.22);
    transition: background var(--tm-ease), box-shadow var(--tm-ease);
    text-decoration: none;
    white-space: nowrap; overflow: hidden;
  }
  .tm-new:hover { background: var(--tm-accent-700); box-shadow: 0 4px 14px rgba(37,99,235,.3); color: #fff; }
  .tm-new.compact { padding: 7px 14px; font-size: 12px; width: auto; }

  /* Rail nav */
  .tm-nav {
    flex: 1; overflow-y: auto; padding: 8px 8px 16px;
  }
  .tm-nav::-webkit-scrollbar { width: 3px; }
  .tm-nav::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 3px; }

  .tm-nav-sec {
    font-size: 9.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .12em;
    color: var(--tm-muted);
    padding: 12px 10px 5px;
    margin: 0;
    white-space: nowrap; overflow: hidden;
  }

  .tm-nav-item {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px;
    border-radius: var(--tm-r-md);
    color: var(--tm-sub); text-decoration: none;
    font-size: 14px; font-weight: 500;
    margin: 1px 0;
    border: 1px solid transparent;
    transition: all var(--tm-ease);
    white-space: nowrap; overflow: hidden;
    position: relative; min-height: 38px;
  }
  .tm-nav-item:hover { background: var(--tm-accent-l); color: var(--tm-text); text-decoration: none; }
  .tm-nav-item.on {
    background: var(--tm-accent-l);
    color: var(--tm-accent);
    font-weight: 600;
    border-color: var(--tm-accent-200);
  }
  .tm-nav-item i { font-size: 14px; flex-shrink: 0; width: 18px; text-align: center; }
  .tm-nav-item.on i { color: var(--tm-accent); }

  /* Count badges */
  .tm-ncount {
    margin-left: auto;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    color: var(--tm-muted);
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    white-space: nowrap; flex-shrink: 0;
  }
  .tm-ndot {
    margin-left: auto;
    background: var(--tm-red);
    color: #fff;
    font-size: 10px; font-weight: 700;
    padding: 1px 6px; border-radius: 10px;
    white-space: nowrap; flex-shrink: 0;
  }

  .tm-rail-backdrop { display: none; }

  /* ═══════════════════════════════════════════════════
    MAIN AREA
    ═══════════════════════════════════════════════════ */
  .tm-main {
    flex: 1; min-width: 0;
    display: flex; flex-direction: column;
    overflow: hidden;
  }

  /* Compact topbar */
  .tm-compactbar {
    height: var(--tm-bar-h);
    background: var(--tm-panel);
    border-bottom: 1px solid var(--tm-border);
    display: flex; align-items: center;
    padding: 0 14px; gap: 8px;
    flex-shrink: 0;
    box-shadow: 0 1px 0 var(--tm-border);
  }
  .tm-compact-title {
    display: flex; align-items: baseline; gap: 8px;
    overflow: hidden;
  }
  .tm-compact-title b {
    font-size: 14px; font-weight: 700; color: var(--tm-text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .tm-compact-title span {
    font-size: 11px; color: var(--tm-muted); white-space: nowrap;
  }
  .tm-compact-spacer { flex: 1; }

  /* Toolbar button */
  .tm-tbtn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: var(--tm-r-sm);
    border: 1px solid var(--tm-border);
    background: var(--tm-panel); color: var(--tm-sub);
    font-size: 12px; font-weight: 500;
    cursor: pointer; white-space: nowrap;
    font-family: var(--tm-font); text-decoration: none;
    transition: all var(--tm-ease);
  }
  .tm-tbtn:hover {
    background: var(--tm-accent-l);
    border-color: var(--tm-accent-200);
    color: var(--tm-accent);
    text-decoration: none;
  }
  .tm-tbtn i { font-size: 11px; }

  /* View segmented control */
  .tm-viewseg {
    display: flex;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    border-radius: 9px; padding: 3px; gap: 1px;
  }
  .tm-viewseg a {
    display: flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 7px;
    font-size: 11px; font-weight: 500;
    color: var(--tm-sub); text-decoration: none;
    white-space: nowrap; transition: all var(--tm-ease);
  }
  .tm-viewseg a.on {
    background: var(--tm-panel); color: var(--tm-text);
    font-weight: 600; box-shadow: var(--tm-shadow);
  }
  .tm-viewseg a:hover:not(.on) { color: var(--tm-text); }

  /* Options panel */
  .tm-options-panel {
    background: var(--tm-panel);
    border-bottom: 1px solid var(--tm-border);
    padding: 10px 14px;
    flex-shrink: 0;
  }
  .tm-options-form {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  }
  .tm-filter-select {
    height: 34px; padding: 0 28px 0 10px;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394A3B8'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 9px center;
    border: 1px solid var(--tm-border); border-radius: var(--tm-r-sm);
    font-size: 12px; font-family: var(--tm-font); color: var(--tm-text);
    background-color: var(--tm-surface);
    outline: none; cursor: pointer;
    transition: border-color var(--tm-ease);
  }
  .tm-filter-select:focus { border-color: var(--tm-accent); box-shadow: 0 0 0 3px var(--tm-accent-l); background-color: var(--tm-panel); }

  /* Body area */
  .tm-body {
    flex: 1; overflow: auto; display: flex; flex-direction: column;
  }
  .tm-body.pad { padding: 20px; overflow: auto; }
  .tm-body.pad::-webkit-scrollbar { width: 5px; }
  .tm-body.pad::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 4px; }

  /* ═══════════════════════════════════════════════════
    SEARCH (shared)
    ═══════════════════════════════════════════════════ */
  .tm-search {
    display: flex; align-items: center; gap: 7px;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-sm);
    padding: 6px 10px;
    transition: border-color var(--tm-ease), box-shadow var(--tm-ease);
  }
  .tm-search:focus-within {
    border-color: var(--tm-accent);
    box-shadow: 0 0 0 3px var(--tm-accent-l);
    background: var(--tm-panel);
  }
  .tm-search i { color: var(--tm-muted); font-size: 12px; flex-shrink: 0; }
  .tm-search input {
    border: none; outline: none; background: transparent;
    font-size: 12px; color: var(--tm-text); font-family: var(--tm-font); width: 180px;
  }
  .tm-search input::placeholder { color: var(--tm-muted); }

  /* ═══════════════════════════════════════════════════
    SHARED FORM ELEMENTS
    ═══════════════════════════════════════════════════ */
  .tm-input, .tm-select, .tm-textarea {
    width: 100%; padding: 8px 11px;
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-sm);
    font-size: 14px; color: var(--tm-text);
    background: var(--tm-panel); font-family: var(--tm-font);
    transition: border-color var(--tm-ease), box-shadow var(--tm-ease);
    outline: none;
  }
  .tm-input:focus, .tm-select:focus, .tm-textarea:focus {
    border-color: var(--tm-accent);
    box-shadow: 0 0 0 3px var(--tm-accent-l);
  }
  .tm-input::placeholder, .tm-textarea::placeholder { color: var(--tm-muted); }
  .tm-textarea { resize: vertical; min-height: 80px; }
  .tm-field-error { font-size: 11px; color: var(--tm-red); margin-top: 3px; display: block; }

  /* Field label wrapper */
  .tm-field { display: flex; flex-direction: column; gap: 5px; }
  .tm-field > span, .tm-field > legend {
    font-size: 12px; font-weight: 600; color: var(--tm-sub);
  }
  .tm-field.full { grid-column: 1 / -1; }

  /* Form grid */
  .tm-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  /* Shared action buttons */
  .blade-primary-action {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px; border-radius: var(--tm-r-sm);
    background: var(--tm-accent); color: #fff;
    border: none; font-size: 14px; font-weight: 600;
    cursor: pointer; text-decoration: none; font-family: var(--tm-font);
    box-shadow: 0 2px 8px rgba(37,99,235,.2);
    transition: background var(--tm-ease), box-shadow var(--tm-ease);
    white-space: nowrap;
  }
  .blade-primary-action:hover { background: var(--tm-accent-700); box-shadow: 0 4px 14px rgba(37,99,235,.3); color: #fff; text-decoration: none; }

  .blade-secondary-action {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: var(--tm-r-sm);
    background: var(--tm-panel); color: var(--tm-sub);
    border: 1px solid var(--tm-border-2);
    font-size: 14px; font-weight: 500;
    cursor: pointer; text-decoration: none; font-family: var(--tm-font);
    transition: all var(--tm-ease); white-space: nowrap;
  }
  .blade-secondary-action:hover {
    background: var(--tm-accent-l); border-color: var(--tm-accent-200);
    color: var(--tm-accent); text-decoration: none;
  }
  .blade-secondary-action.is-danger:hover { background: var(--tm-red-l); border-color: #FCA5A5; color: var(--tm-red); }

  .blade-danger-action {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: var(--tm-r-sm);
    background: var(--tm-red-l); color: var(--tm-red);
    border: 1px solid #FECACA;
    font-size: 14px; font-weight: 500;
    cursor: pointer; text-decoration: none; font-family: var(--tm-font);
    transition: background var(--tm-ease);
  }
  .blade-danger-action:hover { background: #FEE2E2; }

  /* Status pill */
  .blade-status-pill {
    display: inline-flex; align-items: center;
    font-size: 10px; font-weight: 600;
    padding: 3px 9px; border-radius: 20px;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    color: var(--tm-sub); white-space: nowrap;
  }

  /* Priority chips */
  .tm-pri {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 600;
    white-space: nowrap;
  }
  .pdot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
  .tm-pri-low    .pdot { background: var(--tm-low); }
  .tm-pri-medium .pdot { background: var(--tm-medium); }
  .tm-pri-high   .pdot { background: var(--tm-high); }
  .tm-pri-urgent .pdot { background: var(--tm-urgent); }
  .tm-pri-critical .pdot { background: var(--tm-critical); }

  .tm-pri-low    { color: var(--tm-low); }
  .tm-pri-medium { color: var(--tm-medium); }
  .tm-pri-high   { color: var(--tm-high); }
  .tm-pri-urgent { color: var(--tm-urgent); }
  .tm-pri-critical { color: var(--tm-critical); }

  /* Priority chip row (create modal) */
  .tm-prichip-row { display: flex; gap: 6px; flex-wrap: wrap; }
  .tm-prichip-choice input { display: none; }
  .tm-prichip {
    display: inline-flex; align-items: center;
    padding: 4px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    border: 1.5px solid transparent;
    background: var(--tm-surface); color: var(--tm-sub);
    transition: all var(--tm-ease);
  }
  .tm-prichip-choice input:checked + .tm-prichip { border-color: currentColor; }
  .tm-prichip.is-low    { color: var(--tm-low);    } .tm-prichip-choice input:checked + .tm-prichip.is-low    { background: var(--tm-green-l); }
  .tm-prichip.is-medium { color: var(--tm-medium); } .tm-prichip-choice input:checked + .tm-prichip.is-medium { background: var(--tm-accent-l); }
  .tm-prichip.is-high   { color: var(--tm-high);   } .tm-prichip-choice input:checked + .tm-prichip.is-high   { background: var(--tm-orange-l); }
  .tm-prichip.is-urgent { color: var(--tm-urgent); } .tm-prichip-choice input:checked + .tm-prichip.is-urgent { background: var(--tm-red-l); }
  .tm-prichip.is-critical { color: var(--tm-critical); } .tm-prichip-choice input:checked + .tm-prichip.is-critical { background: var(--tm-violet-l); }

  /* Owner avatar */
  .tm-card-owner {
    width: 28px; height: 28px; border-radius: 8px;
    background: linear-gradient(135deg, #4F46E5, #818cf8);
    color: #fff; font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; letter-spacing: .02em;
  }

  /* Tag */
  .tm-tag {
    display: inline-flex; align-items: center;
    font-size: 10px; font-weight: 600;
    padding: 2px 8px; border-radius: 6px;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    color: var(--tm-sub); white-space: nowrap;
  }

  /* Icon button */
  .tm-iconbtn {
    width: 30px; height: 30px;
    display: flex; align-items: center; justify-content: center;
    border-radius: var(--tm-r-sm); border: 1px solid var(--tm-border);
    background: transparent; color: var(--tm-sub);
    cursor: pointer; font-size: 14px; text-decoration: none;
    font-family: var(--tm-font);
    transition: all var(--tm-ease);
  }
  .tm-iconbtn:hover { background: var(--tm-accent-l); border-color: var(--tm-accent-200); color: var(--tm-accent); text-decoration: none; }
  .tm-iconbtn.is-danger:hover { background: var(--tm-red-l); border-color: #FECACA; color: var(--tm-red); }

  /* Empty copy */
  .tm-empty-copy { font-size: 14px; color: var(--tm-muted); font-style: italic; margin: 0; padding: 8px 0; }

  /* ═══════════════════════════════════════════════════
    KANBAN BOARD
    ═══════════════════════════════════════════════════ */
  .tm-board-shell {
    display: flex;
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
    overflow: hidden;
  }
  .tm-kanban-viewport {
    flex: 1 1 auto;
    min-width: 0;
    min-height: 0;
    max-height: calc(100vh - 165px);
    overflow-x: auto;
    overflow-y: hidden;
  }
  .tm-kanban-track {
    display: flex;
    align-items: stretch;
    gap: 14px;
    width: max-content;
    min-width: 100%;
    height: 100%;
    max-height: 100%;
    padding: 12px 18px 28px;
    box-sizing: border-box;
  }
  .tm-kanban {
    flex: 1; display: flex; gap: 12px;
    overflow-x: auto; overflow-y: hidden;
    padding: 16px;
    align-items: stretch;
  }
  .tm-kanban::-webkit-scrollbar { height: 5px; }
  .tm-kanban::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 4px; }

  /* Column */
  .tm-col {
    width: 310px; flex: 0 0 310px;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg);
    display: flex; flex-direction: column;
    height: calc(100vh - 220px) !important;
    max-height: calc(100vh - 220px) !important;
    min-height: 380px !important;
    overflow: hidden !important;
  }

  /* Column header */
  .tm-col-head {
    display: flex; align-items: center; gap: 8px;
    padding: 11px 14px;
    border-bottom: 1px solid var(--tm-border);
    background: var(--tm-panel);
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
  }
  .tm-col-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .tm-col-title { font-size: 12px; font-weight: 700; color: var(--tm-text); flex: 1; }
  .tm-col-count {
    font-size: 10px; font-weight: 700;
    background: var(--tm-surface);
    border: 1px solid var(--tm-border);
    color: var(--tm-muted);
    padding: 1px 7px; border-radius: 10px;
  }

  /* Column body (scrollable) */
  .tm-col-body {
    flex: 1 1 auto !important; min-height: 0 !important; height: 100% !important; overflow-y: auto !important; overscroll-behavior: contain; padding: 10px;
    display: flex !important; flex-direction: column !important; gap: 10px !important;
    scrollbar-width: thin !important;
  }
  .tm-col-body::-webkit-scrollbar { width: 6px !important; }
  .tm-col-body::-webkit-scrollbar-track { background: transparent !important; }
  .tm-col-body::-webkit-scrollbar-thumb { background: rgba(100,116,139,0.4) !important; border-radius: 4px !important; }
  .tm-col-body::-webkit-scrollbar-thumb:hover { background: rgba(100,116,139,0.7) !important; }

  /* ── TASK CARD ── */
  .tm-card {
    flex: 0 0 auto !important;
    flex-shrink: 0 !important;
    min-height: max-content !important;
    background: var(--tm-panel);
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-md);
    overflow: hidden;
    transition: box-shadow var(--tm-ease), border-color var(--tm-ease);
  }
  .tm-card:hover { box-shadow: var(--tm-shadow-md); border-color: var(--tm-accent-200); }

  .tm-card-link {
    display: block; padding: 12px 14px;
    text-decoration: none; color: inherit;
  }

  .tm-card-top {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 7px;
  }
  .tm-card-id {
    font-size: 10px; font-weight: 700;
    color: var(--tm-muted);
    letter-spacing: .04em;
  }
  .tm-card-title {
    font-size: 14px; font-weight: 600; color: var(--tm-text);
    line-height: 1.4; margin: 0 0 7px;
  }
  .tm-card-tags { display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px; }

  .tm-card-foot {
    display: flex; align-items: center; gap: 7px;
    border-top: 1px solid var(--tm-border);
    padding-top: 9px; margin-top: 2px;
  }
  .tm-card-meta {
    display: flex; align-items: center; gap: 4px;
    font-size: 11px; color: var(--tm-sub);
    flex: 1;
  }
  .tm-card-meta i { font-size: 11px; }
  .tm-card-meta.due-over { color: var(--tm-red); }

  /* Mini ring progress */
  .tm-subprog { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--tm-muted); }
  .tm-miniring {
    width: 14px; height: 14px; border-radius: 50%;
    background: conic-gradient(var(--tm-accent) calc(var(--p) * 1%), var(--tm-border) 0%);
    flex-shrink: 0;
  }

  /* ═══════════════════════════════════════════════════
    LIST / TABLE VIEW
    ═══════════════════════════════════════════════════ */
  .tm-grid-wrap { flex: 1; overflow: auto; padding: 0; }
  .tm-grid-wrap::-webkit-scrollbar { width: 5px; height: 5px; }
  .tm-grid-wrap::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 4px; }

  .tm-table {
    width: 100%; border-collapse: collapse;
    font-size: 14px; min-width: 640px;
  }
  .tm-table thead tr {
    background: var(--tm-surface);
    border-bottom: 1px solid var(--tm-border);
  }
  .tm-table th {
    padding: 9px 14px;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    color: var(--tm-muted); text-align: left;
    white-space: nowrap;
  }
  .tm-table tbody tr {
    border-bottom: 1px solid var(--tm-border);
    background: var(--tm-panel);
    transition: background var(--tm-ease);
  }
  .tm-table tbody tr:hover { background: var(--tm-accent-l); }
  .tm-table td { padding: 10px 14px; vertical-align: middle; }
  .tm-td-title { text-decoration: none; color: var(--tm-text); }
  .tm-td-title b { display: block; font-size: 11px; color: var(--tm-muted); font-weight: 600; }
  .tm-td-title .sub { display: block; font-size: 14px; font-weight: 500; color: var(--tm-text); }

  /* Pagination */
  .tm-pagination { padding: 12px 16px; border-top: 1px solid var(--tm-border); flex-shrink: 0; background: var(--tm-panel); }

  /* ═══════════════════════════════════════════════════
    CALENDAR VIEW
    ═══════════════════════════════════════════════════ */
  .tm-cal { flex: 1; overflow: auto; display: flex; flex-direction: column; }
  .tm-calendar-title {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px;
    background: var(--tm-panel); border-bottom: 1px solid var(--tm-border);
    flex-shrink: 0;
  }
  .tm-calendar-title h2 { font-size: 16px; font-weight: 700; color: var(--tm-text); margin: 0; }
  .tm-calendar-title p  { font-size: 12px; color: var(--tm-muted); margin: 2px 0 0; }
  .tm-calendar-nav { display: flex; align-items: center; gap: 6px; }

  .tm-cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    flex: 1; overflow-y: auto;
    min-width: 600px;
  }
  .tm-cal-dow {
    padding: 7px 10px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
    color: var(--tm-muted);
    background: var(--tm-panel);
    border-bottom: 1px solid var(--tm-border);
    border-right: 1px solid var(--tm-border);
    text-align: center;
  }
  .tm-cal-cell {
    border-right: 1px solid var(--tm-border);
    border-bottom: 1px solid var(--tm-border);
    padding: 4px 5px; min-height: 90px;
    background: var(--tm-panel);
    vertical-align: top; position: relative;
  }
  .tm-cal-cell.dim { background: var(--tm-surface); }
  .tm-cal-cell.today { background: #FAFCFF; }
  .tm-cal-cell:nth-child(7n) { border-right: none; }
  .tm-cal-date { text-align: right; font-size: 12px; font-weight: 600; color: var(--tm-sub); margin-bottom: 3px; }
  .tm-cal-cell.today .tm-cal-date span {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--tm-accent); color: #fff; font-weight: 700;
  }
  .tm-cal-task {
    display: block;
    font-size: 10px; font-weight: 600;
    background: var(--tm-accent); color: #fff;
    border-radius: 4px; padding: 2px 5px;
    margin-bottom: 2px;
    overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
    text-decoration: none;
  }
  .tm-cal-task:hover { opacity: .85; }

  /* ═══════════════════════════════════════════════════
    DASHBOARD
    ═══════════════════════════════════════════════════ */
  .tm-dashboard-heading {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
  }
  .tm-dashboard-heading h1 { font-size: 20px; font-weight: 700; color: var(--tm-text); margin: 0 0 3px; }
  .tm-dashboard-heading p  { font-size: 14px; color: var(--tm-muted); margin: 0; }

  /* KPI strip */
  .tm-dash-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px; margin-bottom: 20px;
  }
  .blade-dashboard-kpi {
    display: flex; flex-direction: column;
    background: var(--tm-panel);
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg);
    padding: 16px 18px;
    text-decoration: none; color: inherit;
    transition: box-shadow var(--tm-ease), border-color var(--tm-ease);
  }
  .blade-dashboard-kpi:hover { box-shadow: var(--tm-shadow-md); border-color: var(--tm-accent-200); text-decoration: none; }
  .tm-dashboard-stat { gap: 8px; }

  .tm-stat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0; margin-bottom: 6px;
  }
  .tm-stat-icon.is-accent  { background: #EEF4FF;  color: var(--tm-accent); }
  .tm-stat-icon.is-violet  { background: var(--tm-violet-l); color: var(--tm-violet); }
  .tm-stat-icon.is-green   { background: var(--tm-green-l);  color: var(--tm-green); }
  .tm-stat-icon.is-red     { background: var(--tm-red-l);    color: var(--tm-red); }
  .tm-stat-icon.is-orange  { background: var(--tm-orange-l); color: var(--tm-orange); }
  .tm-stat-icon.is-blue    { background: var(--tm-accent-100); color: var(--tm-accent); }

  .blade-dashboard-kpi small { font-size: 11px; color: var(--tm-muted); font-weight: 600; }
  .blade-dashboard-kpi strong { font-size: 26px; font-weight: 700; color: var(--tm-text); letter-spacing: -.03em; line-height: 1; }

  /* Dashboard panels */
  .tm-dashboard-panels { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
  .tm-dashboard-top-panels { margin-bottom: 14px; }
  @media (max-width: 900px) { .tm-dashboard-panels { grid-template-columns: 1fr; } }

  /* Dashboard card */
  .blade-dashboard-card {
    background: var(--tm-panel);
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg);
    overflow: hidden;
  }
  .blade-dashboard-section-title {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
    padding: 14px 18px 12px;
    border-bottom: 1px solid var(--tm-border);
  }
  .blade-dashboard-section-title h2 { font-size: 14px; font-weight: 700; color: var(--tm-text); margin: 0; }
  .blade-dashboard-section-title small { font-size: 11px; color: var(--tm-muted); white-space: nowrap; margin-top: 2px; }
  .blade-dashboard-section-title a { font-size: 12px; color: var(--tm-accent); text-decoration: none; font-weight: 600; white-space: nowrap; }
  .blade-dashboard-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--tm-muted); margin-bottom: 2px; }

  /* Trend chart */
  .tm-trend-card { padding: 0; }
  .tm-trend-chart { padding: 12px 18px 0; }
  .tm-trend-chart svg { width: 100%; height: 80px; display: block; }
  .tm-trend-area { fill: rgba(37,99,235,.06); stroke: none; }
  .tm-trend-line { fill: none; stroke: var(--tm-accent); stroke-width: 2.5; stroke-linejoin: round; stroke-linecap: round; }
  .tm-trend-labels { display: flex; justify-content: space-between; padding: 8px 0 14px; }
  .tm-trend-labels span { text-align: center; flex: 1; }
  .tm-trend-labels b { display: block; font-size: 14px; font-weight: 700; color: var(--tm-text); }
  .tm-trend-labels small { display: block; font-size: 10px; color: var(--tm-muted); }

  /* Donut chart */
  .tm-status-card { padding: 0; }
  .tm-status-donut {
    width: 96px; height: 96px; border-radius: 50%;
    background: conic-gradient(var(--status-gradient));
    display: flex; align-items: center; justify-content: center;
    margin: 16px auto 12px;
    position: relative;
  }
  .tm-status-donut::after {
    content: ''; position: absolute;
    width: 62px; height: 62px;
    background: var(--tm-panel); border-radius: 50%;
  }
  .tm-status-donut span {
    position: relative; z-index: 1; text-align: center;
  }
  .tm-status-donut b { display: block; font-size: 16px; font-weight: 700; color: var(--tm-text); }
  .tm-status-donut small { display: block; font-size: 10px; color: var(--tm-muted); }

  .tm-status-breakdown { padding: 0 18px 14px; }
  .tm-status-breakdown a {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 0; border-bottom: 1px solid var(--tm-border);
    text-decoration: none; color: var(--tm-text);
    font-size: 12px; transition: background var(--tm-ease);
  }
  .tm-status-breakdown a:last-child { border-bottom: none; }
  .tm-status-breakdown a:hover { color: var(--tm-accent); text-decoration: none; }
  .tm-status-breakdown a span:nth-child(2) { flex: 1; }
  .tm-status-breakdown a b { font-weight: 700; color: var(--tm-text); }
  .tm-status-breakdown a small { color: var(--tm-muted); font-size: 11px; }
  .tm-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

  /* Workload */
  .tm-workload-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 18px; border-bottom: 1px solid var(--tm-border);
  }
  .tm-workload-row:last-child { border-bottom: none; }
  .wl-name { font-size: 12px; font-weight: 600; color: var(--tm-text); width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; }
  .tm-wl-bar {
    flex: 1; height: 6px; border-radius: 4px;
    background: var(--tm-surface); border: 1px solid var(--tm-border);
    display: flex; overflow: hidden;
  }
  .tm-wl-seg { display: block; height: 100%; transition: width .4s ease; }
  .tm-wl-seg.is-complete { background: var(--tm-green); }
  .tm-wl-seg.is-progress { background: var(--tm-accent); }
  .tm-workload-row > b { font-size: 12px; font-weight: 700; color: var(--tm-text); width: 28px; text-align: right; }

  /* Approval queue */
  .tm-approval-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 18px; border-bottom: 1px solid var(--tm-border);
    text-decoration: none; color: inherit;
    transition: background var(--tm-ease);
  }
  .tm-approval-row:last-child { border-bottom: none; }
  .tm-approval-row:hover { background: var(--tm-accent-l); text-decoration: none; }
  .tm-approval-row b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-approval-row small { display: block; font-size: 11px; color: var(--tm-muted); }

  /* Recent tasks */
  .tm-recent-task-card { margin-top: 14px; }
  .tm-recent-task-list { padding: 0 0 6px; }
  .tm-recent-task-list a {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 18px; border-bottom: 1px solid var(--tm-border);
    text-decoration: none; color: inherit;
    transition: background var(--tm-ease);
  }
  .tm-recent-task-list a:last-child { border-bottom: none; }
  .tm-recent-task-list a:hover { background: var(--tm-accent-l); }
  .tm-recent-task-list b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-recent-task-list small { display: block; font-size: 11px; color: var(--tm-muted); }

  /* ═══════════════════════════════════════════════════
    TASK DRAWER
    ═══════════════════════════════════════════════════ */
  /* Legacy drawer geometry is intentionally inactive. The authoritative drawer
     contract is Vite-managed in resources/css/task-drawer.css. */
  @media not all {
  .tm-drawer-scrim {
    position: fixed; inset: 0; z-index: 400;
    background: rgba(15,23,42,.28);
    display: flex; justify-content: flex-end;
  }

  .tm-drawer {
    width: 780px; max-width: 100vw; height: 100%;
    background: var(--tm-bg);
    box-shadow: -4px 0 36px rgba(15,23,42,.14);
    display: flex; flex-direction: column;
    overflow: hidden;
    animation: slideInRight .22s ease;
  }
  @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

  /* Drawer header */
  .tm-dr-head {
    background: var(--tm-panel);
    border-bottom: 1px solid var(--tm-border);
    padding: 14px 18px;
    flex-shrink: 0;
  }
  .tm-dr-crumb {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 8px; flex-wrap: wrap;
  }
  .tm-dr-id {
    font-size: 10px; font-weight: 700;
    color: var(--tm-muted); letter-spacing: .06em;
    padding: 2px 7px; border-radius: 5px;
    background: var(--tm-surface); border: 1px solid var(--tm-border);
  }
  .tm-dr-actions { display: flex; align-items: center; gap: 5px; margin-left: auto; }

  .tm-dr-title { font-size: 17px; font-weight: 700; line-height: 1.3; margin: 0 0 10px; color: var(--tm-text); }

  .tm-dr-statusbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
  }
  .tm-statbtn {
    display: flex; align-items: center; gap: 5px;
    cursor: pointer; background: none; border: none;
    font-family: var(--tm-font);
  }
  .tm-statbtn i { font-size: 10px; color: var(--tm-muted); }
  .tm-due-inline {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; font-weight: 600; color: var(--tm-sub);
  }
  .tm-due-inline i { font-size: 12px; }
  .tm-progress-inline {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 700; color: var(--tm-accent);
  }
  .tm-progress-inline i {
    display: block; width: 60px; height: 4px;
    border-radius: 3px; background: var(--tm-border);
    position: relative; overflow: hidden; font-style: normal;
  }
  .tm-progress-inline i::after {
    content: ''; position: absolute;
    left: 0; top: 0; bottom: 0;
    width: var(--progress, 0%);
    background: var(--tm-accent); border-radius: 3px;
    transition: width .4s ease;
  }

  /* Action menu */
  .tm-action-menu-wrap { position: relative; }
  .tm-action-menu {
    position: absolute; top: calc(100% + 6px); right: 0;
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg); box-shadow: var(--tm-shadow-lg);
    padding: 5px; min-width: 180px; z-index: 50; overflow: hidden;
  }
  .tm-action-menu form button, .tm-action-menu a {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 12px; border-radius: var(--tm-r-sm);
    font-size: 14px; font-weight: 500; color: var(--tm-text-2);
    background: none; border: none; cursor: pointer; width: 100%;
    font-family: var(--tm-font); text-decoration: none;
    transition: background var(--tm-ease);
  }
  .tm-action-menu form button:hover, .tm-action-menu a:hover { background: var(--tm-surface); color: var(--tm-text); }
  .tm-action-menu form.is-danger button:hover { background: var(--tm-red-l); color: var(--tm-red); }
  .tm-action-menu form button i { font-size: 14px; color: var(--tm-muted); width: 16px; text-align: center; }

  /* Drawer body: main + aside */
  .tm-dr-body { flex: 1; display: flex; overflow: hidden; }
  .tm-dr-main { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
  .tm-dr-side {
    width: 220px; flex-shrink: 0;
    background: var(--tm-surface);
    border-left: 1px solid var(--tm-border);
    overflow-y: auto; padding: 16px 14px;
  }
  .tm-dr-side::-webkit-scrollbar { width: 3px; }
  .tm-dr-side::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 3px; }

  /* Tabs */
  .tm-dr-tabs {
    display: flex; gap: 0;
    background: var(--tm-panel);
    border-bottom: 1px solid var(--tm-border);
    overflow-x: auto; flex-shrink: 0;
  }
  .tm-dr-tabs::-webkit-scrollbar { display: none; }
  .tm-dr-tab {
    display: flex; align-items: center; gap: 6px;
    padding: 10px 16px;
    font-size: 12px; font-weight: 600; color: var(--tm-sub);
    cursor: pointer; border: none; background: none; font-family: var(--tm-font);
    border-bottom: 2px solid transparent;
    white-space: nowrap; transition: all var(--tm-ease);
  }
  .tm-dr-tab:hover { color: var(--tm-text); }
  .tm-dr-tab.on { color: var(--tm-accent); border-bottom-color: var(--tm-accent); }
  .tm-dr-tab .cnt {
    font-size: 10px; font-weight: 700; background: var(--tm-surface);
    border: 1px solid var(--tm-border); color: var(--tm-muted);
    padding: 1px 6px; border-radius: 10px;
  }

  /* Tab content area (scrollable) */
  .tm-dr-main section {
    flex: 1; overflow-y: auto; padding: 16px 18px;
  }
  .tm-dr-main section::-webkit-scrollbar { width: 4px; }
  .tm-dr-main section::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 4px; }

  /* Detail card */
  .tm-detail-stack { display: flex; flex-direction: column; gap: 12px; }
  .tm-detail-card {
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-md); overflow: hidden;
  }
  .tm-detail-card-head {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--tm-border);
    background: var(--tm-surface);
  }
  .tm-detail-card-head h3 { font-size: 12px; font-weight: 700; color: var(--tm-sub); margin: 0; flex: 1; }
  .tm-detail-card-head i { font-size: 14px; color: var(--tm-muted); }
  .tm-detail-card-head .cnt { font-size: 10px; font-weight: 700; color: var(--tm-muted); padding: 1px 6px; background: var(--tm-border); border-radius: 10px; }
  .tm-detail-card-body { padding: 14px; }
  .tm-desc { font-size: 13.5px; line-height: 1.75; color: var(--tm-text-2); white-space: pre-wrap; margin: 0; }
  .tm-card-tags { display: flex; gap: 6px; flex-wrap: wrap; }

  /* Attachment rows */
  .tm-attachment-row {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 0; border-bottom: 1px solid var(--tm-border);
  }
  .tm-attachment-row:last-child { border-bottom: none; }
  .tm-attachment-row > span { flex: 1; min-width: 0; }
  .tm-attachment-row b { display: block; font-size: 12px; font-weight: 600; color: var(--tm-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .tm-attachment-row small { display: block; font-size: 11px; color: var(--tm-muted); }
  .tm-attachment-icon { width: 30px; height: 30px; border-radius: 7px; background: var(--tm-surface); border: 1px solid var(--tm-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--tm-muted); flex-shrink: 0; }

  .tm-attachment-upload {
    display: flex; gap: 8px; align-items: center;
    margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--tm-border);
  }
  .tm-file-drop {
    display: flex; align-items: center; gap: 8px; flex: 1;
    padding: 8px 12px; border: 1.5px dashed var(--tm-border);
    border-radius: var(--tm-r-sm); cursor: pointer;
    font-size: 12px; color: var(--tm-sub);
    transition: border-color var(--tm-ease);
  }
  .tm-file-drop:hover { border-color: var(--tm-accent); color: var(--tm-accent); }
  .tm-file-drop input { display: none; }
  .tm-file-drop i { font-size: 15px; }

  /* Empty panel */
  .tm-empty-panel {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 14px 0;
  }
  .tm-empty-ic { width: 32px; height: 32px; border-radius: 8px; background: var(--tm-surface); border: 1px solid var(--tm-border); display: flex; align-items: center; justify-content: center; font-size: 14px; color: var(--tm-muted); flex-shrink: 0; }
  .tm-empty-panel b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-empty-panel small { display: block; font-size: 12px; color: var(--tm-muted); margin-top: 2px; line-height: 1.45; }

  /* Subtasks / checklist rows */
  .tm-sub-row {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 0; border-bottom: 1px solid var(--tm-border);
  }
  .tm-sub-row:last-child { border-bottom: none; }
  .tm-sub-check {
    width: 18px; height: 18px; border-radius: 5px; flex-shrink: 0;
    border: 1.5px solid var(--tm-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 10px; color: transparent;
    transition: all var(--tm-ease);
  }
  .tm-sub-check.on { background: var(--tm-green); border-color: var(--tm-green); color: #fff; }
  .tm-sub-title { flex: 1; font-size: 14px; color: var(--tm-text); }
  .tm-sub-title.done { text-decoration: line-through; color: var(--tm-muted); }

  /* Checklist progress */
  .tm-check-progress {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 12px;
  }
  .tm-check-progress b { font-size: 12px; font-weight: 700; color: var(--tm-text); white-space: nowrap; }
  .tm-check-progress span {
    flex: 1; height: 5px; background: var(--tm-border); border-radius: 3px; overflow: hidden;
  }
  .tm-check-progress span i {
    display: block; height: 100%; background: var(--tm-green); border-radius: 3px;
    transition: width .4s ease; font-style: normal;
  }
  .tm-check-row {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 0; border-bottom: 1px solid var(--tm-border);
  }
  .tm-check-row:last-child { border-bottom: none; }
  .tm-check-row input[type="checkbox"] { width: 15px; height: 15px; flex-shrink: 0; accent-color: var(--tm-accent); }
  .tm-check-row input[type="text"] {
    flex: 1; border: none; background: transparent; outline: none;
    font-size: 14px; color: var(--tm-text); font-family: var(--tm-font);
  }
  .tm-check-options { display: flex; flex-direction: column; gap: 6px; }
  .tm-check-options label { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; }
  .tm-check-options input { accent-color: var(--tm-accent); }

  /* Comments */
  .tm-comment {
    display: flex; gap: 10px; padding: 12px 0;
    border-bottom: 1px solid var(--tm-border);
  }
  .tm-comment:last-of-type { border-bottom: none; }
  .tm-comment > div { flex: 1; }
  .tm-comment b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-comment time { display: block; font-size: 11px; color: var(--tm-muted); margin: 2px 0 6px; }
  .tm-comment p { font-size: 14px; color: var(--tm-text-2); line-height: 1.6; margin: 0; }

  .tm-comment-form { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
  .tm-comment-input-wrap { position: relative; }
  .tm-comment-input-wrap .tm-iconbtn { position: absolute; right: 8px; bottom: 8px; }

  /* Mention popover */
  .tm-mention-popover {
    position: absolute; bottom: calc(100% + 6px); left: 0; right: 0;
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg); box-shadow: var(--tm-shadow-lg);
    overflow: hidden; z-index: 50;
  }
  .tm-mention-popover header { padding: 10px 14px; border-bottom: 1px solid var(--tm-border); }
  .tm-mention-popover header b { display: block; font-size: 12px; font-weight: 700; color: var(--tm-text); }
  .tm-mention-popover header small { font-size: 11px; color: var(--tm-muted); }
  .tm-mention-list { max-height: 200px; overflow-y: auto; padding: 4px; }
  .tm-mention-list button {
    display: flex; align-items: center; gap: 9px;
    width: 100%; padding: 8px 10px; border-radius: var(--tm-r-sm);
    background: none; border: none; cursor: pointer; font-family: var(--tm-font);
    transition: background var(--tm-ease);
  }
  .tm-mention-list button:hover { background: var(--tm-surface); }
  .tm-mention-list b { display: block; font-size: 12px; font-weight: 600; color: var(--tm-text); }
  .tm-mention-list small { font-size: 11px; color: var(--tm-muted); }

  /* Activity */
  .tm-act-row {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid var(--tm-border);
    text-decoration: none; color: inherit;
    transition: background var(--tm-ease);
  }
  .tm-act-row:last-child { border-bottom: none; }
  .tm-act-row:hover { color: var(--tm-accent); }
  .tm-act-ic {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--tm-surface); border: 1px solid var(--tm-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: var(--tm-muted); flex-shrink: 0; margin-top: 1px;
  }
  .tm-act-row b { display: block; font-size: 12px; font-weight: 700; color: var(--tm-text); }
  .tm-act-row small { display: block; font-size: 11px; color: var(--tm-muted); margin-top: 1px; }
  .tm-act-row time { margin-left: auto; font-size: 11px; color: var(--tm-muted); white-space: nowrap; flex-shrink: 0; }

  /* Time logs */
  .tm-time-row {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 10px 0; border-bottom: 1px solid var(--tm-border);
  }
  .tm-time-row:last-child { border-bottom: none; }
  .tm-time-row b { display: block; font-size: 14px; font-weight: 700; color: var(--tm-text); }
  .tm-time-row small { display: block; font-size: 11px; color: var(--tm-muted); margin-top: 1px; }
  .tm-time-row time { font-size: 11px; color: var(--tm-muted); white-space: nowrap; }

  /* Add-line form */
  .tm-addline-form {
    display: flex; gap: 8px; margin-top: 12px; align-items: center;
    padding-top: 12px; border-top: 1px solid var(--tm-border);
  }

  /* Dependencies */
  .tm-dep-add { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
  .tm-dependency-list { display: flex; flex-direction: column; gap: 4px; }
  .tm-inline-actions { display: flex; gap: 8px; margin-top: 10px; }

  /* Drawer side meta */
  .tm-meta-block {
    margin-bottom: 16px; padding-bottom: 16px;
    border-bottom: 1px solid var(--tm-border);
  }
  .tm-meta-block:last-child { border-bottom: none; margin-bottom: 0; }
  .tm-meta-block > span { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--tm-muted); margin-bottom: 5px; }
  .tm-meta-block > b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-person-line { display: flex; align-items: center; gap: 8px; }

  .tm-timeline-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 8px; margin-top: 4px; }
  .tm-timeline-meta small { font-size: 10px; color: var(--tm-muted); font-weight: 600; }
  .tm-timeline-meta b { font-size: 12px; font-weight: 600; color: var(--tm-text); }

  /* Assignee popover */
  .tm-assignee-block { position: relative; }
  .tm-assignee-add {
    width: 22px; height: 22px; border-radius: 6px;
    border: 1.5px dashed var(--tm-border);
    background: none; cursor: pointer; color: var(--tm-muted);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; margin-left: 4px;
    transition: all var(--tm-ease);
  }
  .tm-assignee-add:hover { border-color: var(--tm-accent); color: var(--tm-accent); }
  .tm-assignee-popover {
    position: absolute; top: calc(100% + 6px); left: 0; right: 0;
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg); box-shadow: var(--tm-shadow-lg);
    overflow: hidden; z-index: 50;
  }
  .tm-people-pop-list { max-height: 200px; overflow-y: auto; }
  .tm-people-pop-list form button {
    display: flex; align-items: center; gap: 9px;
    width: 100%; padding: 9px 12px; border-radius: 0;
    background: none; border: none; cursor: pointer;
    font-family: var(--tm-font); text-align: left;
    border-bottom: 1px solid var(--tm-border);
    transition: background var(--tm-ease);
  }
  .tm-people-pop-list form button:hover { background: var(--tm-accent-l); }
  .tm-people-pop-list b { display: block; font-size: 12px; font-weight: 600; color: var(--tm-text); }
  .tm-people-pop-list small { font-size: 11px; color: var(--tm-muted); }
  .tm-people-pop-list .fa-check { margin-left: auto; color: var(--tm-accent); font-size: 11px; }

  /* Watch button */
  .tm-watchbtn {
    display: flex; align-items: center; gap: 7px;
    width: 100%; padding: 7px 12px; border-radius: var(--tm-r-sm);
    border: 1px solid var(--tm-border);
    background: var(--tm-surface); color: var(--tm-sub);
    font-size: 12px; font-weight: 600; cursor: pointer;
    font-family: var(--tm-font); transition: all var(--tm-ease);
  }
  .tm-watchbtn:hover { border-color: var(--tm-accent); color: var(--tm-accent); background: var(--tm-accent-l); }
  .tm-watchbtn.on { background: var(--tm-accent-l); border-color: var(--tm-accent-200); color: var(--tm-accent); }

  /* Approval decision block */
  .tm-approval-decision {
    background: var(--tm-orange-l);
    border: 1px solid #FDE68A; border-radius: var(--tm-r-md);
    padding: 14px; margin-bottom: 16px;
  }
  .tm-approval-decision > span { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--tm-orange); }
  .tm-approval-decision > b { display: block; font-size: 14px; font-weight: 700; color: var(--tm-text); margin: 4px 0 2px; }
  .tm-approval-decision > small { display: block; font-size: 12px; color: var(--tm-sub); margin-bottom: 10px; }
  .tm-decision-actions { display: flex; gap: 8px; margin-top: 10px; }

  /* Control menu (status/priority dropdowns) */
  .tm-control-menu { position: relative; }
  .tm-control-menu-list {
    position: absolute; top: calc(100% + 4px); left: 0;
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg); box-shadow: var(--tm-shadow-lg);
    padding: 5px; min-width: 160px; z-index: 50; overflow: hidden;
  }
  .tm-control-menu-list form button {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; padding: 7px 10px; border-radius: var(--tm-r-sm);
    background: none; border: none; cursor: pointer; font-family: var(--tm-font);
    transition: background var(--tm-ease);
  }
  .tm-control-menu-list form button:hover { background: var(--tm-surface); }
  .tm-control-menu-list .fa-check { color: var(--tm-accent); font-size: 11px; }

  /* ═══════════════════════════════════════════════════
    CREATE MODAL
    ═══════════════════════════════════════════════════ */
  }

  .tm-modal-scrim {
    position: fixed; inset: 0; z-index: 500;
    background: rgba(15,23,42,.32);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 40px 16px 24px; overflow-y: auto;
  }
  .tm-modal {
    background: var(--tm-panel);
    border-radius: 16px;
    box-shadow: var(--tm-shadow-lg);
    width: 680px; max-width: 100%;
    display: flex; flex-direction: column;
    max-height: calc(100vh - 64px); overflow: hidden;
    animation: modalIn .18s ease;
  }
  @keyframes modalIn {
    from { opacity: 0; transform: scale(.96) translateY(-8px); }
    to   { opacity: 1; transform: none; }
  }
  .tm-modal-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--tm-border); flex-shrink: 0;
    background: var(--tm-surface);
  }
  .tm-modal-head h2 { font-size: 16px; font-weight: 700; color: var(--tm-text); margin: 0 0 2px; }
  .tm-modal-head p  { font-size: 12px; color: var(--tm-muted); margin: 0; }

  .tm-modal-body { overflow-y: auto; flex: 1; padding: 20px; }
  .tm-modal-body::-webkit-scrollbar { width: 4px; }
  .tm-modal-body::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 4px; }

  .tm-modal-foot {
    display: flex; align-items: center; gap: 8px;
    padding: 12px 20px;
    border-top: 1px solid var(--tm-border); flex-shrink: 0;
    background: var(--tm-surface);
  }
  .tm-modal-note { flex: 1; font-size: 11px; color: var(--tm-muted); }

  /* People search picker */
  .tm-people-select {
    border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-md); overflow: hidden;
  }
  .tm-people-select > summary {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; cursor: pointer; list-style: none;
    background: var(--tm-surface);
    font-size: 14px;
  }
  .tm-people-select > summary::-webkit-details-marker { display: none; }
  .tm-people-select > summary:hover { background: var(--tm-accent-l); }
  .tm-people-select > summary small { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--tm-muted); }
  .tm-people-select > summary b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-people-select[open] > summary { border-bottom: 1px solid var(--tm-border); }
  .tm-people-select > summary i { color: var(--tm-muted); font-size: 11px; }

  .people-search-picker { border: none; padding: 0; margin: 0; }
  .people-search-picker legend { display: none; }
  .people-search-results { max-height: 200px; overflow-y: auto; padding: 4px; }
  .people-search-results::-webkit-scrollbar { width: 3px; }
  .people-search-results::-webkit-scrollbar-thumb { background: var(--tm-border-2); border-radius: 3px; }
  .people-search-results label {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 10px; border-radius: var(--tm-r-sm);
    cursor: pointer; transition: background var(--tm-ease);
  }
  .people-search-results label:hover { background: var(--tm-surface); }
  .people-search-results input[type="radio"] { accent-color: var(--tm-accent); flex-shrink: 0; }
  .people-search-results b { display: block; font-size: 12px; font-weight: 600; color: var(--tm-text); }
  .people-search-results small { font-size: 11px; color: var(--tm-muted); }

  /* Advanced section */
  .tm-advanced {
    border: 1px solid var(--tm-border); border-radius: var(--tm-r-md); overflow: hidden;
  }
  .tm-advanced > summary {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 14px; cursor: pointer; list-style: none;
    background: var(--tm-surface); gap: 10px;
  }
  .tm-advanced > summary::-webkit-details-marker { display: none; }
  .tm-advanced > summary:hover { background: var(--tm-accent-l); }
  .tm-advanced > summary span { display: flex; align-items: center; gap: 9px; }
  .tm-advanced > summary b { font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-advanced > summary small { font-size: 11px; color: var(--tm-muted); }
  .tm-advanced > summary i.fa-chevron-down { font-size: 10px; color: var(--tm-muted); }
  .tm-advanced[open] > summary { border-bottom: 1px solid var(--tm-border); }
  .tm-advanced-grid { padding: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

  /* ═══════════════════════════════════════════════════
    INSIGHTS (activity, reports, analytics, templates, settings)
    ═══════════════════════════════════════════════════ */
  .tm-action-row { display: flex; gap: 8px; align-items: center; }
  .tm-activity-filters { display: flex; gap: 4px; }
  .tm-activity-filters a {
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 500;
    color: var(--tm-sub); text-decoration: none;
    border: 1px solid var(--tm-border); background: var(--tm-surface);
    transition: all var(--tm-ease);
  }
  .tm-activity-filters a:hover { color: var(--tm-accent); border-color: var(--tm-accent-200); background: var(--tm-accent-l); }
  .tm-activity-filters a.on { background: var(--tm-accent-l); border-color: var(--tm-accent-200); color: var(--tm-accent); font-weight: 600; }

  .tm-insight-card { padding: 0; }

  /* Templates */
  .tm-tpl-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
  .tm-tpl-card {
    background: var(--tm-panel); border: 1px solid var(--tm-border);
    border-radius: var(--tm-r-lg); padding: 18px;
    display: flex; flex-direction: column; gap: 10px;
  }
  .tm-tpl-ic { width: 36px; height: 36px; border-radius: 10px; background: var(--tm-accent-l); color: var(--tm-accent); display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .tm-template-title { display: flex; align-items: center; gap: 8px; }
  .tm-template-title h3 { font-size: 14px; font-weight: 700; color: var(--tm-text); margin: 0; flex: 1; }
  .tm-tpl-card p { font-size: 12.5px; color: var(--tm-sub); line-height: 1.5; margin: 0; }
  .tm-tpl-card ol { padding-left: 16px; margin: 0; }
  .tm-tpl-card li { font-size: 12px; color: var(--tm-sub); padding: 2px 0; }

  /* Settings tabs */
  .tm-settings-tabs {
    display: flex; gap: 4px; margin-bottom: 16px;
    background: var(--tm-surface); border: 1px solid var(--tm-border);
    border-radius: 10px; padding: 4px;
    width: fit-content;
  }
  .tm-settings-tabs a {
    padding: 6px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 500; color: var(--tm-sub);
    text-decoration: none; transition: all var(--tm-ease);
  }
  .tm-settings-tabs a.on { background: var(--tm-panel); color: var(--tm-accent); font-weight: 600; box-shadow: var(--tm-shadow); }

  .tm-status-settings { padding: 0 18px 14px; }
  .tm-status-settings > div {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 0; border-bottom: 1px solid var(--tm-border);
    font-size: 14px;
  }
  .tm-status-settings > div:last-child { border-bottom: none; }
  .tm-status-settings > div i { color: var(--tm-muted); }
  .tm-status-settings > div small { color: var(--tm-muted); font-size: 12px; }
  .tm-status-settings > div b { color: var(--tm-text); }

  .tm-workflow-settings { padding: 0 18px 14px; }
  .tm-workflow-settings > div {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 0; border-bottom: 1px solid var(--tm-border);
  }
  .tm-workflow-settings > div:last-child { border-bottom: none; }
  .tm-workflow-settings > div > span { flex: 1; }
  .tm-workflow-settings b { display: block; font-size: 14px; font-weight: 600; color: var(--tm-text); }
  .tm-workflow-settings small { display: block; font-size: 11px; color: var(--tm-muted); margin-top: 2px; }

  .tm-setting-toggle {
    width: 36px; height: 20px; border-radius: 12px;
    background: var(--tm-border); flex-shrink: 0;
    position: relative; display: block;
    transition: background var(--tm-ease);
  }
  .tm-setting-toggle::after {
    content: ''; position: absolute;
    top: 2px; left: 2px; width: 16px; height: 16px;
    border-radius: 50%; background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.15);
    transition: transform var(--tm-ease);
  }
  .tm-setting-toggle.on { background: var(--tm-green); }
  .tm-setting-toggle.on::after { transform: translateX(16px); }

  /* Permission table */
  .tm-permission-scroll { overflow-x: auto; padding: 0 18px 18px; }
  .tm-permission-table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 600px; }
  .tm-permission-table th, .tm-permission-table td {
    padding: 9px 12px; border-bottom: 1px solid var(--tm-border); text-align: center;
  }
  .tm-permission-table thead th { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--tm-muted); background: var(--tm-surface); }
  .tm-permission-table tbody th { text-align: left; font-weight: 600; color: var(--tm-text); }
  .tm-permission-table .fa-check.is-yes { color: var(--tm-green); }
  .tm-permission-table .fa-xmark.is-no  { color: var(--tm-border-2); }
  .tm-own-pill { font-size: 9px; font-weight: 700; padding: 1px 6px; background: var(--tm-orange-l); color: var(--tm-orange); border-radius: 10px; border: 1px solid #FDE68A; }

  /* Recurrence */
  .tm-recurrence-info { display: flex; gap: 10px; }

  @media (max-width: 900px) {
    .tm-rail {
      display: flex;
      position: fixed;
      inset: 0 auto 0 0;
      width: min(280px, 86vw);
      height: 100dvh;
      z-index: 620;
      transform: translateX(-102%);
      transition: transform .2s ease;
      box-shadow: var(--tm-shadow-lg);
    }
    .tm-rail.workspace-open { transform: translateX(0); }
    .tm-rail.workspace-hidden { display: flex; }
    .tm-rail-backdrop {
      display: block;
      position: fixed;
      inset: 0;
      z-index: 610;
      border: 0;
      background: var(--b360-overlay, rgba(15, 23, 42, .48));
    }
    .tm-main { width: 100%; }
  }

  /* Accessibility */
  .sr-only {
    position: absolute; width: 1px; height: 1px;
    padding: 0; margin: -1px; overflow: hidden;
    clip: rect(0,0,0,0); white-space: nowrap; border: 0;
  }
  @media (prefers-reduced-motion: reduce) {
    .tm-drawer, .tm-modal { animation: none; }
  }
</style>
@section('content')
<section
    class="tm b360-collaboration-screen b360-task-workspace"
    x-data="taskWorkspace"
    x-bind:class="fullScreen ? 'tm-fullscreen' : ''"
    x-on:keydown.escape.window="escapeWorkspace"
    x-on:resize.window="handleWorkspaceResize"
    data-open-create="{{ ($filters['create'] ?? false) || old('form_context') === 'create' ? '1' : '0' }}"
    data-user-id="{{ auth()->id() }}"
    data-company-id="{{ auth()->user()?->company_id ?? $tasks->getCollection()->first()?->company_id }}"
    data-task-version="{{ $tasks->getCollection()->max(fn($task) => $task->updated_at?->timestamp) ?? 0 }}"
    data-realtime-enabled="{{ config('broadcasting.default') === 'reverb' ? '1' : '0' }}"
    data-reverb-key="{{ config('broadcasting.connections.reverb.key') }}"
    data-reverb-host="{{ config('broadcasting.connections.reverb.options.host') }}"
    data-reverb-port="{{ config('broadcasting.connections.reverb.options.port') }}"
    data-reverb-scheme="{{ config('broadcasting.connections.reverb.options.scheme') }}"
    aria-label="Task Management"
>
    <div class="tm-live-update" x-show="stale" x-cloak role="status"><span><i class="fa-solid fa-arrows-rotate"></i><b>Task updates are available.</b> Refresh to view the latest changes.</span><button type="button" class="blade-primary-action" x-on:click="refresh">Refresh</button></div>
    <aside id="taskWorkspaceRail" class="tm-rail" x-bind:class="railClasses" x-ref="taskRail" tabindex="-1" aria-label="Task workspace">
        <div class="tm-rail-head">
            @if ($canCreateTask)
                <button type="button" class="tm-new" x-on:click="openCreate"><i class="fa-solid fa-plus" aria-hidden="true"></i> New task</button>
            @endif
        </div>
        {{-- <button type="button" class="cal-control-btn" x-on:click="toggleFullScreen" aria-label="Full screen"><i class="fa-solid fa-expand"></i><span class="sr-only">Full screen</span></button> --}}
        <nav class="tm-nav">
            <p class="tm-nav-sec">Workspace</p>
            @foreach (['dashboard' => 'fa-table-cells-large', 'mine' => 'fa-list-check', 'assigned-to-me' => 'fa-user-plus', 'assigned-by-me' => 'fa-paper-plane', 'team' => 'fa-users', 'department' => 'fa-building', 'all' => 'fa-layer-group'] as $key => $icon)
                <a class="tm-nav-item {{ $scope === $key ? 'on' : '' }}" href="{{ route('collaboration.tasks.index', $taskQuery(['scope' => $key, 'page' => null])) }}">
                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i><span>{{ $scopeLabels[$key] }}</span>
                    @if ($key !== 'dashboard')<span class="tm-ncount">{{ $taskScopeCounts[$key] ?? 0 }}</span>@endif
                </a>
            @endforeach

            <p class="tm-nav-sec">Due & status</p>
            @foreach (['due-today' => 'fa-calendar-day', 'due-week' => 'fa-calendar-week', 'overdue' => 'fa-triangle-exclamation', 'pending' => 'fa-clock', 'completed' => 'fa-circle-check', 'archived' => 'fa-box-archive'] as $key => $icon)
                <a class="tm-nav-item {{ $scope === $key ? 'on' : '' }}" href="{{ route('collaboration.tasks.index', $taskQuery(['scope' => $key, 'page' => null])) }}">
                    <i class="fa-solid {{ $icon }}" aria-hidden="true"></i><span>{{ $scopeLabels[$key] }}</span>
                    @if ($key === 'overdue' && ($taskScopeCounts[$key] ?? 0) > 0)<span class="tm-ndot">{{ $taskScopeCounts[$key] }}</span>@else<span class="tm-ncount">{{ $taskScopeCounts[$key] ?? 0 }}</span>@endif
                </a>
            @endforeach

            @if ($canManageTasks)
                <p class="tm-nav-sec">Insights & setup</p>
                @foreach (['activity' => 'fa-clock-rotate-left', 'reports' => 'fa-file-lines', 'analytics' => 'fa-chart-line', 'templates' => 'fa-clone', 'settings' => 'fa-gear'] as $key => $icon)
                    <a class="tm-nav-item {{ $scope === $key ? 'on' : '' }}" href="{{ route('collaboration.tasks.index', $taskQuery(['scope' => $key, 'page' => null])) }}">
                        <i class="fa-solid {{ $icon }}" aria-hidden="true"></i><span>{{ $scopeLabels[$key] }}</span>
                    </a>
                @endforeach
            @endif
        </nav>
    </aside>
    <button type="button" class="tm-rail-backdrop" x-show="compact && railOpen" x-cloak x-on:click="closeRail" aria-label="Close task workspace" tabindex="-1"></button>

    <div class="tm-main">
        <header class="tm-compactbar">
            <button type="button" class="tm-tbtn" x-on:click="toggleRail" aria-controls="taskWorkspaceRail" x-bind:aria-expanded="railExpanded">
                <i class="fa-solid fa-layer-group" aria-hidden="true"></i><span x-text="railOpen ? 'Hide workspace' : 'Show workspace'"></span>
            </button>
            <div class="tm-compact-title">
                <b>{{ $scopeLabels[$scope] ?? 'Task Management' }}</b>
                <span>{{ number_format($tasks->total()) }} task{{ $tasks->total() === 1 ? '' : 's' }}</span>
            </div>
            <div class="tm-compact-spacer"></div>

            @if (! in_array($scope, ['dashboard', 'activity', 'reports', 'analytics', 'templates', 'settings'], true))
                <div class="tm-viewseg" role="tablist" aria-label="Task view">
                    @foreach (['board' => ['fa-table-columns', 'Board'], 'list' => ['fa-list', 'List'], 'calendar' => ['fa-calendar', 'Calendar']] as $key => [$icon, $label])
                        <a role="tab" aria-selected="{{ $view === $key ? 'true' : 'false' }}" class="{{ $view === $key ? 'on' : '' }}" href="{{ route('collaboration.tasks.index', $taskQuery(['view' => $key, 'page' => null])) }}"><i class="fa-solid {{ $icon }}"></i>{{ $label }}</a>
                    @endforeach
                </div>
                <button type="button" class="tm-tbtn" x-on:click="toggleOptions" x-bind:aria-expanded="optionsOpen.toString()"><i class="fa-solid fa-sliders"></i><span x-text="optionsOpen ? 'Hide options' : 'Show options'"></span></button>
            @endif
            <button type="button" class="tm-tbtn" x-on:click="toggleFullScreen"><i class="fa-solid fa-expand"></i><span x-text="fullScreen ? 'Exit Full Screen' : 'Full Screen'"></span></button>
            @if ($canCreateTask)<button type="button" class="tm-new compact tm-compact-create" x-bind:class="railOpen ? 'rail-is-open' : ''" x-on:click="openCreate"><i class="fa-solid fa-plus"></i> New task</button>@endif
        </header>

        @if (session('status'))<div class="blade-alert blade-alert-success tm-workspace-alert">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="blade-alert blade-alert-danger tm-workspace-alert"><strong>Check the highlighted inputs.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        @if (! in_array($scope, ['dashboard', 'activity', 'reports', 'analytics', 'templates', 'settings'], true))
            <section class="tm-options-panel" x-show="optionsOpen" x-cloak aria-label="Task options">
                <form method="GET" action="{{ route('collaboration.tasks.index') }}" class="tm-options-form">
                    <input type="hidden" name="scope" value="{{ $scope }}"><input type="hidden" name="view" value="{{ $view }}">
                    <label class="tm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search tasks..."></label>
                    <select class="tm-select tm-filter-select" name="priority" aria-label="Priority"><option value="">All priorities</option>@foreach ($priorities as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? null) === $value)>{{ $label }}</option>@endforeach</select>
                    <!-- <select class="tm-select tm-filter-select" name="project_id" aria-label="Project"><option value="">All projects</option>@foreach ($projects as $project)<option value="{{ $project->id }}" @selected(($filters['project_id'] ?? null) == $project->id)>{{ $project->code }}</option>@endforeach</select> -->
                    <button class="tm-tbtn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
                    <a class="tm-tbtn" href="{{ route('collaboration.tasks.export', $taskQuery(['format' => 'csv'])) }}"><i class="fa-solid fa-download"></i> CSV</a>
                    <a class="tm-tbtn" href="{{ route('collaboration.tasks.index', ['scope' => $scope, 'view' => $view]) }}">Reset</a>
                </form>
            </section>
        @endif

        @if ($scope === 'dashboard')
            @include('collaboration.tasks.partials.dashboard')
        @elseif (in_array($scope, ['activity', 'reports', 'analytics', 'templates', 'settings'], true))
            @include('collaboration.tasks.partials.insights')
        @else
            @include('collaboration.tasks.partials.views')
        @endif
    </div>

    @include('collaboration.tasks.partials.create-modal')
    @if ($selectedTask)@include('collaboration.tasks.partials.drawer')@endif
</section>
@endsection
