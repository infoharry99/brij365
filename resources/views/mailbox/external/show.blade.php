@extends('layouts.builder360-classic')

@php
    $activeFolder = $folder?->id;
    $folderIcons  = [
        'inbox'   => 'fa-inbox',
        'sent'    => 'fa-paper-plane',
        'drafts'  => 'fa-file-lines',
        'archive' => 'fa-box-archive',
        'spam'    => 'fa-shield-halved',
        'trash'   => 'fa-trash',
        'all'     => 'fa-envelope',
    ];
    $avatarColor = function (string $seed): string {
        $colors = ['#4F46E5','#F5852B','#059669','#DC2626','#D97706','#0891B2','#7C3AED','#BE185D','#C2410C','#0F766E'];
        return $colors[abs(crc32($seed)) % count($colors)];
    };
@endphp

@section('title', $mailboxAccount->email . ' | Mailbox')

@push('styles')
    <style>
        /* ════════════════════════════════════════════════════════════════
        MAILBOX — ARCTIC CLARITY — COMPLETE END-TO-END
        White + Light Blue · System fonts · CSP-safe · No external CDN
        ════════════════════════════════════════════════════════════════ */

        /* ── 1. Stop page / body from scrolling ── */
        html,
        body.b360-classic { height:100%; overflow:hidden !important; }

        /* ── 2. Shell fills viewport ── */
        .b360-shell {
          height: 100vh;
          overflow: hidden;
          display: flex;
        }
        .b360-sidebar {
          flex-shrink: 0;
          height: 100vh;
          overflow-y: auto;
          overflow-x: hidden;
        }
        .b360-main {
        flex: 1;
        min-width: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        }
        .b360-topbar { flex-shrink: 0; }
        .b360-content {
        flex: 1;
        min-height: 0;
        padding: 0 !important;
        overflow: hidden !important;
        display: flex;
        flex-direction: column;
        }

        /* ════════════════════════════════
        TOKENS
        ════════════════════════════════ */
        .mbx-screen {
        --c-bg:       #F0F4FA;
        --c-surface:  #FFFFFF;
        --c-surface2: #F7F9FC;
        --c-border:   #E2E8F2;
        --c-border2:  #C7D5EA;
        --c-accent:   #F5852B;
        --c-accent-l: #EEF4FF;
        --c-accent-1: #DBEAFE;
        --c-accent-2: #BFDBFE;
        --c-accent-7: #1D4ED8;
        --c-text:     #0F172A;
        --c-text2:    #334155;
        --c-sub:      #475569;
        --c-muted:    #94A3B8;
        --c-green:    #059669;
        --c-red:      #DC2626;
        --c-amber:    #D97706;
        --c-shadow:   0 1px 3px rgba(15,23,42,.07),0 1px 2px rgba(15,23,42,.04);
        --c-shadow-m: 0 4px 16px rgba(15,23,42,.09);
        --r-sm: 8px; --r-md:10px; --r-lg:14px;
        --font: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
        }

        /* ════════════════════════════════
        THREE-PANE SCREEN
        ════════════════════════════════ */
        .mbx-screen {
        flex: 1;
        min-height: 0;
        display: flex;
        flex-direction: row;
        overflow: hidden;
        background: var(--c-bg);
        font-family: var(--font);
        font-size: 13px;
        color: var(--c-text);
        -webkit-font-smoothing: antialiased;
        }
        .mbx-screen * { box-sizing: border-box; }

        /* ════════════════════════════════
        RAIL
        ════════════════════════════════ */
        .mbx-rail {
        width: 220px;
        flex-shrink: 0;
        height: 100%;
        background: var(--c-surface);
        border-right: 1px solid var(--c-border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        z-index: 10;
        }
        /* Blue top stripe */
        .mbx-rail::before {
        content: '';
        display: block;
        height: 3px;
        flex-shrink: 0;
        background: linear-gradient(90deg,var(--c-accent),#60A5FA);
        }

        /* compose */
        .mbx-compose {
        display: flex; align-items: center; justify-content: center; gap: 8px;
        margin: 14px 12px 10px;
        padding: 10px 16px;
        background: var(--c-accent); color: #fff;
        border: none; border-radius: var(--r-md);
        font-size: 14px; font-weight: 700; cursor: pointer;
        font-family: var(--font); text-decoration: none;
        box-shadow: 0 3px 12px rgba(37,99,235,.25);
        transition: background .14s, transform .12s;
        flex-shrink: 0;
        }
        .mbx-compose:hover { background:var(--c-accent-7); transform:translateY(-1px); color:#fff; text-decoration:none; }

        /* Account switcher */
        .mbx-acct-wrap { margin:0 10px 8px; flex-shrink:0; }
        .mbx-acct-sum {
        display: flex; align-items: center; gap: 8px;
        padding: 9px 10px;
        border: 1px solid var(--c-border); border-radius: var(--r-md);
        background: var(--c-surface2); cursor: pointer; list-style: none;
        transition: all .14s;
        }
        .mbx-acct-sum::-webkit-details-marker { display: none; }
        .mbx-acct-sum:hover { border-color:var(--c-accent-2); background:var(--c-accent-l); }
        details[open] .mbx-acct-sum { border-color:var(--c-accent-2); background:var(--c-accent-l); }
        .mbx-dot { width:8px; height:8px; border-radius:50%; background:var(--c-border2); flex-shrink:0; }
        .mbx-dot.on { background:var(--c-green); box-shadow:0 0 0 2px rgba(5,150,105,.2); }
        .mbx-ai { flex:1; min-width:0; }
        .mbx-ai strong { display:block; font-size:12.5px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--c-text); }
        .mbx-ai small   { display:block; font-size:11px; color:var(--c-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-acct-drop { margin-top:4px; background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--r-md); overflow:hidden; box-shadow:var(--c-shadow-m); }
        .mbx-acct-drop a { display:flex; align-items:center; gap:8px; padding:9px 12px; text-decoration:none; color:var(--c-text2); font-size:12.5px; border-bottom:1px solid var(--c-border); transition:background .13s; }
        .mbx-acct-drop a:last-child { border-bottom:none; }
        .mbx-acct-drop a:hover { background:var(--c-accent-l); color:var(--c-accent); text-decoration:none; }
        .mbx-acct-drop a.ia { background:var(--c-accent-l); }
        .mbx-acct-drop a.ia strong { color:var(--c-accent); }

        /* Folder nav */
        .mbx-nav { flex:1; min-height:0; overflow-y:auto; padding:4px 8px 12px; }
        .mbx-nav::-webkit-scrollbar { width:3px; }
        .mbx-nav::-webkit-scrollbar-thumb { background:var(--c-border); border-radius:3px; }
        .mbx-nav-lbl { font-size:9.5px; font-weight:800; text-transform:uppercase; letter-spacing:.1em; color:var(--c-muted); padding:12px 8px 5px; display:block; }
        .mbx-fl {
        display:flex; align-items:center; gap:9px;
        padding:8px 10px; border-radius:var(--r-md); margin:1px 0;
        text-decoration:none; color:var(--c-sub); font-size:13px; font-weight:500;
        border:1px solid transparent; transition:all .14s; position:relative; overflow:hidden;
        }
        .mbx-fl:hover { background:var(--c-accent-l); color:var(--c-text); text-decoration:none; }
        .mbx-fl.ia { background:var(--c-accent-l); color:var(--c-accent); font-weight:600; border-color:var(--c-accent-2); }
        .mbx-fl.ia::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--c-accent); border-radius:0 3px 3px 0; }
        .mbx-fl i { font-size:13px; width:16px; text-align:center; flex-shrink:0; }
        .mbx-fl span { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-fb { margin-left:auto; background:var(--c-accent-1); color:var(--c-accent-7); font-size:10px; font-weight:700; padding:1px 7px; border-radius:10px; font-style:normal; white-space:nowrap; }
        .mbx-fb.r { background:#FEE2E2; color:var(--c-red); }

        /* Sync card */
        .mbx-sync { margin:10px 10px 0; padding:10px 12px; background:var(--c-surface2); border:1px solid var(--c-border); border-radius:var(--r-md); display:flex; align-items:center; gap:8px; flex-shrink:0; flex-wrap:wrap; }
        .mbx-sdot { width:7px; height:7px; border-radius:50%; background:var(--c-border2); flex-shrink:0; }
        .mbx-sdot.on { background:var(--c-green); }
        .mbx-scopy { flex:1; font-size:11px; color:var(--c-muted); line-height:1.3; min-width:0; }
        .mbx-sbtn { background:none; border:1px solid var(--c-accent-2); border-radius:6px; padding:3px 10px; font-size:11px; font-weight:700; color:var(--c-accent); cursor:pointer; font-family:var(--font); transition:all .14s; white-space:nowrap; }
        .mbx-sbtn:hover { background:var(--c-accent-l); }
        .mbx-serr { margin:5px 10px 8px; padding:8px 11px; background:#FEF2F2; border:1px solid #FECACA; border-radius:var(--r-sm); font-size:11px; color:var(--c-red); line-height:1.4; word-break:break-word; }

        /* ════════════════════════════════
        LIST PANE
        ════════════════════════════════ */
        .mbx-list {
        width: 320px;
        flex-shrink: 0;
        height: 100%;
        background: var(--c-surface);
        border-right: 1px solid var(--c-border);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        }

        .mbx-lhead { display:flex; align-items:center; gap:8px; padding:14px 14px 12px; border-bottom:1px solid var(--c-border); flex-shrink:0; }
        .mbx-ltitle { display:flex; align-items:center; gap:8px; flex:1; min-width:0; }
        .mbx-ltitle h1 { font-size:15px; font-weight:700; color:var(--c-text); margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-lcnt { font-size:10px; font-weight:700; background:var(--c-accent-1); color:var(--c-accent-7); padding:2px 8px; border-radius:20px; white-space:nowrap; }
        .mbx-refr { width:30px; height:30px; display:flex; align-items:center; justify-content:center; border:1px solid var(--c-border); border-radius:7px; background:var(--c-surface); color:var(--c-muted); text-decoration:none; font-size:12px; transition:all .14s; }
        .mbx-refr:hover { background:var(--c-accent-l); border-color:var(--c-accent-2); color:var(--c-accent); }

        .mbx-search { display:flex; align-items:center; gap:7px; margin:9px 12px 6px; padding:7px 11px; background:var(--c-surface2); border:1px solid var(--c-border); border-radius:var(--r-md); flex-shrink:0; transition:all .15s; }
        .mbx-search:focus-within { border-color:var(--c-accent); box-shadow:0 0 0 3px rgba(37,99,235,.10); background:var(--c-surface); }
        .mbx-search i { color:var(--c-muted); font-size:12px; flex-shrink:0; }
        .mbx-search input { flex:1; border:none; outline:none; background:transparent; font-size:13px; color:var(--c-text); font-family:var(--font); min-width:0; }
        .mbx-search input::placeholder { color:var(--c-muted); }
        .mbx-search input[type="hidden"] { display:none; }

        .mbx-chips { display:flex; align-items:center; gap:4px; padding:0 12px 8px; overflow-x:auto; flex-shrink:0; scrollbar-width:none; }
        .mbx-chips::-webkit-scrollbar { display:none; }
        .mbx-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 11px; border-radius:20px; font-size:11.5px; font-weight:500; color:var(--c-sub); background:var(--c-surface2); border:1px solid var(--c-border); text-decoration:none; white-space:nowrap; cursor:pointer; transition:all .13s; font-family:var(--font); }
        .mbx-chip:hover { background:var(--c-accent-l); border-color:var(--c-accent-2); color:var(--c-accent); text-decoration:none; }
        .mbx-chip.on { background:var(--c-accent-l); border-color:var(--c-accent-2); color:var(--c-accent); font-weight:600; }
        .mbx-chip-wrap { position: absolute; }
        .mbx-chip-wrap summary { list-style:none; }
        .mbx-chip-wrap summary::-webkit-details-marker { display:none; }
        .mbx-chip-wrap[open] > summary > .mbx-chip { background:var(--c-accent-l); border-color:var(--c-accent-2); color:var(--c-accent); }
        .mbx-chip-drop { position:absolute; top:calc(100% + 5px); left:0; z-index:60; background:var(--c-surface); border:1px solid var(--c-border); border-radius:var(--r-lg); box-shadow:var(--c-shadow-m); min-width:150px; padding:4px; }
        .mbx-chip-drop a { display:flex; padding:8px 12px; border-radius:var(--r-sm); font-size:13px; color:var(--c-text2); text-decoration:none; transition:background .13s; }
        .mbx-chip-drop a:hover { background:var(--c-accent-l); color:var(--c-accent); }

        .mbx-rows { flex:1; min-height:0; overflow-y:auto; overflow-x:hidden; }
        .mbx-rows::-webkit-scrollbar { width:4px; }
        .mbx-rows::-webkit-scrollbar-thumb { background:var(--c-border2); border-radius:4px; }

        .mbx-row {
        display:flex; align-items:flex-start; gap:9px;
        padding:10px 12px; border-bottom:1px solid #F7F9FC;
        text-decoration:none; color:inherit; cursor:pointer;
        position:relative; transition:background .12s;
        }
        .mbx-row:hover { background:var(--c-surface2); text-decoration:none; }
        .mbx-row.on { background:var(--c-accent-l); border-left:3px solid var(--c-accent); }
        .mbx-row.unr .mbx-rsub { font-weight:700; color:var(--c-text); }
        .mbx-row.unr::after { content:''; position:absolute; top:13px; right:12px; width:6px; height:6px; border-radius:50%; background:var(--c-accent); box-shadow:0 0 0 2px rgba(37,99,235,.2); }
        .mbx-rstar { font-size:13px; color:var(--c-border2); flex-shrink:0; margin-top:2px; line-height:1; }
        .mbx-row.fl .mbx-rstar { color:var(--c-amber); }

        .mbx-av { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; text-transform:uppercase; letter-spacing:-.01em; }

        .mbx-rcopy { flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .mbx-rtop { display:flex; align-items:center; justify-content:space-between; gap:6px; }
        .mbx-rfrom { font-size:13px; font-weight:600; color:var(--c-text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-rtime { font-size:11px; color:var(--c-muted); flex-shrink:0; white-space:nowrap; }
        .mbx-rsub  { font-size:12.5px; font-weight:500; color:var(--c-sub); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-rpre  { font-size:11.5px; color:var(--c-muted); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .mbx-rtag  { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; padding:2px 7px; border-radius:4px; background:var(--c-surface2); border:1px solid var(--c-border); color:var(--c-sub); margin-top:2px; }

        .mbx-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:48px 20px; text-align:center; }
        .mbx-empty-ic { width:48px; height:48px; border-radius:13px; background:var(--c-accent-l); display:flex; align-items:center; justify-content:center; font-size:20px; color:var(--c-accent); }
        .mbx-empty strong { font-size:14px; font-weight:700; color:var(--c-text); }
        .mbx-empty span   { font-size:13px; color:var(--c-muted); max-width:200px; line-height:1.5; }
        .mbx-lpag { padding:10px 12px; border-top:1px solid var(--c-border); flex-shrink:0; }

        /* ════════════════════════════════
        READING PANE
        ════════════════════════════════ */
        .mbx-reading {
        flex: 1;
        min-width: 0;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;    /* clips — does NOT scroll */
        background: var(--c-bg);
        }

        /* Subject header — pinned */
        .mbx-rh {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 15px 20px 13px;
        background: var(--c-surface);
        border-bottom: 1px solid var(--c-border);
        flex-shrink: 0;
        }
        .mbx-rh-copy { flex:1; min-width:0; }
        .mbx-msg-count { font-size:11px; color:var(--c-muted); font-weight:600; margin-bottom:3px; display:flex; align-items:center; gap:5px; }
        .mbx-rh h2 { font-size:15px; font-weight:700; color:var(--c-text); margin:0; line-height:1.35; word-break:break-word; }
        .mbx-rh-pill { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:700; padding:4px 11px; border-radius:20px; background:var(--c-surface2); border:1px solid var(--c-border); color:var(--c-sub); white-space:nowrap; flex-shrink:0; align-self:flex-start; margin-top:4px; }
        .mbx-rh-pill.unr { background:var(--c-accent-1); border-color:var(--c-accent-2); color:var(--c-accent-7); }
        .mbx-rh-pill.unr::before { content:''; width:6px; height:6px; border-radius:50%; background:var(--c-accent); display:inline-block; }

        /* ── Thread: THE ONLY SCROLLING REGION ── */
        .mbx-thread {
        flex: 1;
        min-height: 0;        /* ← without this, flex won't scroll */
        overflow-y: auto;
        overflow-x: hidden;
        padding: 14px 18px 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        -webkit-overflow-scrolling: touch;
        }
        .mbx-thread::-webkit-scrollbar       { width: 5px; }
        .mbx-thread::-webkit-scrollbar-track { background: transparent; }
        .mbx-thread::-webkit-scrollbar-thumb { background: var(--c-border2); border-radius: 4px; }
        .mbx-thread::-webkit-scrollbar-thumb:hover { background: var(--c-accent-2); }

        /* Message card */
        .mbx-card {
        background: var(--c-surface);
        border: 1px solid var(--c-border);
        border-radius: var(--r-lg);
        overflow: hidden;
        flex-shrink: 0;
        transition: box-shadow .15s;
        }
        .mbx-card:hover { box-shadow: var(--c-shadow-m); }
        .mbx-card.cur { border-color: var(--c-accent-2); box-shadow: 0 0 0 3px rgba(37,99,235,.08); }

        /* Card sender row */
        .mbx-cs {
        display:flex; align-items:center; gap:11px;
        padding:12px 16px;
        background:var(--c-surface2); border-bottom:1px solid var(--c-border);
        cursor:pointer; user-select:none; transition:background .14s ease;
        }
        .mbx-cs:hover { background:var(--c-accent-l) !important; }
        .mbx-cs .mbx-av { width:34px; height:34px; border-radius:9px; }
        .mbx-ci { flex:1; min-width:0; }
        .mbx-ci strong { display:block; font-size:13.5px; font-weight:700; color:var(--c-text); }
        .mbx-ci small   { display:block; font-size:12px; color:var(--c-muted); margin-top:1px; }
        .mbx-ct { font-size:11.5px; color:var(--c-muted); white-space:nowrap; flex-shrink:0; }
        .mbx-chevron { font-size:11px; color:var(--c-muted); transition:transform .2s ease; margin-left:8px; flex-shrink:0; }
        .mbx-card.is-expanded .mbx-chevron { transform:rotate(180deg); }
        .mbx-card.is-collapsed .mbx-cs { border-bottom:none; }
        .mbx-card.is-collapsed .mbx-cb,
        .mbx-card.is-collapsed .mbx-ca { display:none !important; }
        .mbx-card.is-collapsed { border-color:var(--c-border); margin-bottom:8px; }

        /* Outbox state pills */
        .mbx-sp { display:inline-flex; align-items:center; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:2px 8px; border-radius:20px; margin-left:8px; vertical-align:middle; }
        .sp-sent  { background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0; }
        .sp-draft { background:var(--c-accent-1); color:var(--c-accent-7); border:1px solid var(--c-accent-2); }
        .sp-fail  { background:#FEE2E2; color:var(--c-red); border:1px solid #FECACA; }
        .sp-sched { background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; }

        /* Card body */
        .mbx-cb { padding:16px 18px; }
        .mbx-plain { font-size:14px; line-height:1.75; color:var(--c-text2); white-space:pre-wrap; word-break:break-word; overflow-wrap:break-word; }
        /* HTML email host */
        .b360-email-shadow-host { min-height:60px; overflow:hidden; width:100%; max-width:100%; }
        .b360-email-tpl { display:none; }
        .b360-email-fallback { font-size:14px; line-height:1.75; color:var(--c-text2); overflow-wrap:break-word; }
        .b360-email-fallback img { max-width:100%; height:auto; }

        /* Card attachments */
        .mbx-ca { padding:10px 16px 13px; border-top:1px solid var(--c-border); display:flex; flex-wrap:wrap; gap:6px; align-items:flex-start; }
        .mbx-ca > strong { width:100%; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--c-muted); margin-bottom:2px; }
        .mbx-ca a { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; background:var(--c-accent-l); border:1px solid var(--c-accent-2); border-radius:var(--r-sm); font-size:12px; font-weight:500; color:var(--c-accent); text-decoration:none; transition:background .13s; }
        .mbx-ca a:hover { background:var(--c-accent-1); }

        /* ── Action footer — ALWAYS PINNED ── */
        .mbx-af {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        padding: 10px 16px;
        background: var(--c-surface);
        border-top: 1px solid var(--c-border);
        flex-shrink: 0;
        min-height: 54px;
        }
        .mbx-af form { margin: 0; }

        /* Buttons */
        .mbx-btn {
        display:inline-flex; align-items:center; gap:6px;
        padding:7px 14px; border-radius:var(--r-sm);
        border:1px solid var(--c-border); background:var(--c-surface);
        font-size:13px; font-weight:500; color:var(--c-text2);
        cursor:pointer; font-family:var(--font); text-decoration:none;
        transition:all .14s; white-space:nowrap;
        }
        .mbx-btn:hover { background:var(--c-accent-l); border-color:var(--c-accent-2); color:var(--c-accent); text-decoration:none; }
        .mbx-btn i { font-size:11px; }
        .mbx-btn.pri { background:var(--c-accent); color:#fff; border-color:var(--c-accent); font-weight:600; box-shadow:0 2px 8px rgba(37,99,235,.22); }
        .mbx-btn.pri:hover { background:var(--c-accent-7); border-color:var(--c-accent-7); color:#fff; box-shadow:0 4px 14px rgba(37,99,235,.30); }
        .mbx-btn.danger { color:var(--c-red); border-color:#FECACA; background:#FFF5F5; }
        .mbx-btn.danger:hover { background:#FEE2E2; border-color:#FCA5A5; color:var(--c-red); }

        /* Empty reading state */
        .mbx-rempty { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; padding:40px; text-align:center; }
        .mbx-rempty-ic { width:60px; height:60px; border-radius:16px; background:var(--c-accent-l); border:1px solid var(--c-accent-2); display:flex; align-items:center; justify-content:center; font-size:26px; color:var(--c-accent); }
        .mbx-rempty strong { font-size:15px; font-weight:700; color:var(--c-text); }
        .mbx-rempty span   { font-size:13px; color:var(--c-muted); max-width:240px; line-height:1.6; }

        /* ════════════════════════════════
        RESPONSIVE
        ════════════════════════════════ */
        @media (max-width:1280px) {
        .mbx-list { width:290px; }
        .mbx-rail { width:200px; }
        }
        @media (max-width:1024px) {
        .mbx-reading:not(.visible) { display:none; }
        .mbx-list { width:auto; flex:1; border-right:none; }
        .mbx-rail { width:185px; }
        }
        @media (max-width:768px) {
        .mbx-rail { display:none; }
        .mbx-screen.has-msg .mbx-list    { display:none; }
        .mbx-screen.has-msg .mbx-reading { display:flex; }
        .mbx-reading { width:100%; }
        }

        /* Accessibility */
        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    </style>
@endpush

@section('content')
<section class="mbx-screen {{ $selected ? 'has-msg' : '' }}" aria-label="Mailbox">

  {{-- ══════════════ RAIL ══════════════ --}}
  <aside class="mbx-rail">

    {{-- Compose --}}
    @can('send', $mailboxAccount)
      <a class="mbx-compose"
         href="{{ route('mailbox.external.show', [$mailboxAccount, 'compose' => 'new']) }}">
        <i class="fa-solid fa-pen" aria-hidden="true"></i> Compose
      </a>
    @endcan

    {{-- Account switcher --}}
    <details class="mbx-acct-wrap" >
      <summary class="mbx-acct-sum">
        <span class="mbx-dot {{ $mailboxAccount->status === 'active' ? 'on' : '' }}"></span>
        <div class="mbx-ai">
          <strong>{{ $mailboxAccount->name }}</strong>
          <small style="display:inline-flex; align-items:center; gap:4px;">
            {{ $mailboxAccount->email }}
            <button type="button" onclick="copyEmailToClipboard('{{ $mailboxAccount->email }}', event)" title="Copy email address" style="background:none; border:none; color:var(--c-muted); cursor:pointer; padding:1px 4px; font-size:11px; border-radius:3px;" onmouseover="this.style.color='var(--c-accent)'" onmouseout="this.style.color='var(--c-muted)'">
              <i class="fa-regular fa-copy" aria-hidden="true"></i>
            </button>
          </small>
        </div>
        <i class="fa-solid fa-chevron-down" style="font-size:10px;color:var(--c-muted)" aria-hidden="true"></i>
      </summary>
      <nav class="mbx-acct-drop" aria-label="Switch account">
        @foreach($availableAccounts as $acct)
          <a href="{{ route('mailbox.external.show', $acct) }}"
             class="{{ $acct->is($mailboxAccount) ? 'ia' : '' }}">
            <span class="mbx-dot {{ $acct->status === 'active' ? 'on' : '' }}"></span>
            <div class="mbx-ai">
              <strong>{{ $acct->name }}</strong>
              <small style="display:inline-flex; align-items:center; gap:4px;">
                {{ $acct->email }}
                <button type="button" onclick="copyEmailToClipboard('{{ $acct->email }}', event)" title="Copy email address" style="background:none; border:none; color:var(--c-muted); cursor:pointer; padding:1px 4px; font-size:11px; border-radius:3px;" onmouseover="this.style.color='var(--c-accent)'" onmouseout="this.style.color='var(--c-muted)'">
                  <i class="fa-regular fa-copy" aria-hidden="true"></i>
                </button>
              </small>
            </div>
            @if($acct->is($mailboxAccount))
              <i class="fa-solid fa-check" style="font-size:11px;color:var(--c-accent);margin-left:auto" aria-hidden="true"></i>
            @endif
          </a>
        @endforeach
        <a href="{{ route('mailbox.accounts.index') }}">
          <i class="fa-solid fa-gear" style="font-size:12px;color:var(--c-muted)" aria-hidden="true"></i>
          <div class="mbx-ai"><strong>Manage accounts</strong></div>
        </a>
      </nav>
    </details>

    {{-- Folder nav --}}
    <nav class="mbx-nav" aria-label="Email folders">
      <span class="mbx-nav-lbl">Mailbox</span>

      @foreach($mailboxAccount->folders->sortBy(fn($f) => $f->special_use === 'inbox' ? 0 : 1) as $f)
        @php $uc = $f->emails()->where('is_deleted', false)->where('is_read', false)->count(); @endphp
        <a href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $f->id]) }}"
           class="mbx-fl {{ $activeFolder === $f->id ? 'ia' : '' }}"
           @if($activeFolder === $f->id) aria-current="page" @endif>
          <i class="fa-solid {{ $folderIcons[$f->special_use] ?? 'fa-folder' }}" aria-hidden="true"></i>
          <span>{{ $f->name }}</span>
          @if($uc)<em class="mbx-fb">{{ number_format($uc) }}</em>@endif
        </a>
      @endforeach

      @can('send', $mailboxAccount)
        @php $dc = $mailboxAccount->outboxMessages()->where('user_id', auth()->id())->whereIn('state', ['draft','scheduled','failed'])->count(); @endphp
        <a href="{{ route('mailbox.drafts.index', $mailboxAccount) }}" class="mbx-fl">
          <i class="fa-solid fa-file-pen" aria-hidden="true"></i>
          <span>Drafts & scheduled</span>
          @if($dc)<em class="mbx-fb">{{ $dc }}</em>@endif
        </a>
      @endcan
    </nav>

    {{-- Sync --}}
    <div class="mbx-sync" id="mbxSyncContainer" data-sync-url="{{ route('mailbox.accounts.sync-json', $mailboxAccount) }}">
      <span class="mbx-sdot {{ $mailboxAccount->status === 'active' ? 'on' : '' }}" id="mbxSyncDot"></span>
      <span class="mbx-scopy" id="mbxSyncCopy">
        {{ $mailboxAccount->last_synced_at
            ? 'Synced ' . $mailboxAccount->last_synced_at->diffForHumans()
            : 'Not synced yet' }}
      </span>
      @can('update', $mailboxAccount)
        <form id="mbxSyncForm" method="POST" action="{{ route('mailbox.accounts.sync', $mailboxAccount) }}">
          @csrf
          <button type="submit" id="mbxSyncBtn" class="mbx-sbtn">Sync now</button>
        </form>
      @endcan
    </div>
    @if($mailboxAccount->last_sync_error)
      <p class="mbx-serr">{{ $mailboxAccount->last_sync_error }}</p>
    @endif

  </aside>{{-- /rail --}}


  {{-- ══════════════ LIST PANE ══════════════ --}}
  <section class="mbx-list">

    <header class="mbx-lhead">
      <div class="mbx-ltitle">
        <i class="fa-solid {{ $folderIcons[$folder?->special_use ?? 'all'] ?? 'fa-envelope' }}"
           style="font-size:15px;color:var(--c-muted)" aria-hidden="true"></i>
        <h1>{{ $folder?->name ?? 'Mailbox' }}</h1>
        <span class="mbx-lcnt">{{ number_format($emails->total()) }}</span>
      </div>
      <a class="mbx-refr" href="{{ request()->fullUrl() }}" aria-label="Refresh">
        <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
      </a>
    </header>

    <form class="mbx-search" method="GET" role="search">
      <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
      <input type="search" name="q"
             value="{{ request('q') }}"
             placeholder="Search sender, subject…"
             aria-label="Search messages">
      <input type="hidden" name="folder" value="{{ $activeFolder }}">
    </form>

    <nav class="mbx-chips" aria-label="Message filters">
      <a class="mbx-chip {{ request()->boolean('unread') ? 'on' : '' }}"
         href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $activeFolder, 'unread' => 1]) }}">
        Unread
      </a>
      <a class="mbx-chip {{ request()->boolean('flagged') ? 'on' : '' }}"
         href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $activeFolder, 'flagged' => 1]) }}">
        Starred
      </a>
      <a class="mbx-chip {{ request()->boolean('attachments') ? 'on' : '' }}"
         href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $activeFolder, 'attachments' => 1]) }}">
        Attachments
      </a>
      <details class="mbx-chip-wrap">
        <summary>
          <span class="mbx-chip">
            Sort <i class="fa-solid fa-chevron-down" style="font-size:9px" aria-hidden="true"></i>
          </span>
        </summary>
        <div class="mbx-chip-drop">
          @foreach(['newest' => 'Newest first', 'oldest' => 'Oldest first', 'subject' => 'Subject', 'sender' => 'Sender'] as $s => $sl)
            <a href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $activeFolder, 'sort' => $s]) }}">{{ $sl }}</a>
          @endforeach
        </div>
      </details>
      <a class="mbx-chip"
         href="{{ route('mailbox.external.show', [$mailboxAccount, 'folder' => $activeFolder]) }}">
        Clear
      </a>
    </nav>

    {{-- Bulk Action Bar --}}
    @php $isTrashView = ($folder?->special_use === 'trash'); @endphp
    <div id="bulkActionBar" style="display:none; align-items:center; justify-content:space-between; background:#FFF7ED; border:1px solid #FFEDD5; padding:8px 14px; margin:6px 10px; border-radius:8px; font-size:12.5px; color:#9A3412; font-weight:600;">
      <div style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" id="selectAllEmailsChk" onclick="toggleSelectAllEmails(this)" style="cursor:pointer; accent-color:var(--c-accent, #F5852B); width:15px; height:15px;">
        <span id="bulkSelectedCount">0 selected</span>
      </div>
      <div style="display:flex; align-items:center; gap:6px;">
        <button type="button" class="mbx-btn danger" onclick="openBulkDeleteDialog({{ $isTrashView ? 'true' : 'false' }})" style="padding:4px 10px; font-size:11.5px;">
          <i class="fa-solid {{ $isTrashView ? 'fa-trash-can' : 'fa-trash' }}" aria-hidden="true"></i>
          {{ $isTrashView ? 'Delete permanently' : 'Delete selected' }}
        </button>
        <button type="button" class="mbx-btn" onclick="submitBulkAction('read')" style="padding:4px 10px; font-size:11.5px;">
          <i class="fa-regular fa-envelope-open" aria-hidden="true"></i> Mark read
        </button>
      </div>
    </div>

    
    <div class="mbx-rows">
      @forelse($emails as $email)
        @php
          /* ── Detect outbox vs IMAP email ── */
          $isOutboxMsg = $email instanceof \App\Models\MailboxOutboxMessage;
    
          if ($isOutboxMsg) {
            /* Outbox: sender = the mailbox account (Me) */
            $fname  = $mailboxAccount->name ?? 'Me';
            $femail = $mailboxAccount->email ?? '';
            $init   = strtoupper(mb_substr($fname, 0, 2));
            $col    = $avatarColor($femail ?: $fname);
    
            /* Recipients as preview prefix */
            $toLine = collect($email->to_addresses ?? [])
              ->map(fn($a) => $a['name'] ?? $a['email'] ?? $a)
              ->filter()
              ->implode(', ');
    
            $subject = $email->subject ?: '(No subject)';
            $preview = $toLine ? 'To: ' . str($toLine)->limit(50) : str(strip_tags($email->text_body ?? ''))->squish()->limit(65);
            $msgTime = $email->sent_at ?? $email->updated_at;
            $isRead  = true;    /* outbox messages have no is_read concept */
            $isFlagged = false;
    
            /* Route to the outbox message detail */
            $msgUrl = route('mailbox.external.show', [
              $mailboxAccount,
              'folder'  => $activeFolder,
              'message' => $email->id,
            ]);
    
            /* State pill */
            $statePill = match($email->state ?? 'draft') {
              'sent'      => ['sp-sent',  'Sent'],
              'scheduled' => ['sp-sched', 'Scheduled'],
              'failed'    => ['sp-fail',  'Failed'],
              default     => ['sp-draft', 'Draft'],
            };
    
          } else {
            /* IMAP email: sender from from_addresses */
            $from    = collect($email->from_addresses ?? [])->first();
            $fname   = $email->thread_participants ?? ($from['name'] ?? $from['email'] ?? 'Unknown');
            $femail  = $from['email'] ?? '';
            $init    = strtoupper(mb_substr($fname, 0, 2));
            $col     = $avatarColor($femail ?: $fname);
            $subject = $email->subject ?: '(No subject)';
            $preview = $email->text_body
              ? str($email->text_body)->squish()->limit(65)
              : str(strip_tags($email->html_body ?? ''))->squish()->limit(65);
            $msgTime   = $email->received_at;
            $isRead    = isset($email->thread_has_unread) ? !$email->thread_has_unread : $email->is_read;
            $isFlagged = isset($email->thread_has_flagged) ? $email->thread_has_flagged : $email->is_flagged;
            $statePill = null;
    
            $msgUrl = route('mailbox.external.show', [
              $mailboxAccount,
              'folder'  => $activeFolder,
              'message' => $email->id,
            ] + request()->only(['q', 'sort', 'unread', 'flagged', 'attachments']));
          }
    
          /* Row CSS classes */
          $cls = implode(' ', array_filter([
            'mbx-row',
            $selected?->id === $email->id ? 'on'  : '',
            !$isRead                      ? 'unr' : '',
            $isFlagged                    ? 'fl'  : '',
          ]));
    
          /* Smart time format */
          $timeDisplay = $msgTime
            ? ($msgTime->isToday()
                ? $msgTime->format('h:i A')
                : ($msgTime->isYesterday()
                    ? 'Yesterday'
                    : $msgTime->format('d M')))
            : '';
        @endphp
    
        <a class="{{ $cls }}"
          href="{{ $msgUrl }}"
          aria-label="{{ $subject }} {{ $isOutboxMsg ? 'to ' . ($toLine ?? '') : 'from ' . $fname }}">
    
          {{-- Checkbox for multi-select bulk actions --}}
          @if(!$isOutboxMsg)
            <input type="checkbox"
                   class="mbx-email-select-chk"
                   value="{{ $email->id }}"
                   onclick="event.stopPropagation(); onEmailCheckChange(this);"
                   aria-label="Select email"
                   style="margin-right:4px; cursor:pointer; accent-color:var(--c-accent, #F5852B); width:15px; height:15px; flex-shrink:0;">
          @endif

          {{-- Star (hide for outbox) --}}
          @if(!$isOutboxMsg)
            <span class="mbx-rstar" aria-hidden="true">{{ $isFlagged ? '★' : '☆' }}</span>
          @else
            <span style="width:13px;flex-shrink:0"></span>
          @endif
    
          {{-- Avatar --}}
          <span class="mbx-av" style="background:{{ $col }}" aria-hidden="true">{{ $init }}</span>
    
          {{-- Content --}}
          <span class="mbx-rcopy">
            <span class="mbx-rtop">
              <strong class="mbx-rfrom">
                {{ $isOutboxMsg ? 'Me' : $fname }}
                @if(!$isOutboxMsg && ($email->thread_count ?? 1) > 1)
                  <span class="mbx-lcnt" style="font-size:10px; padding:1px 6px; margin-left:4px;" title="{{ $email->thread_count }} messages in thread">{{ $email->thread_count }}</span>
                @endif
                @if($statePill)
                  <span class="mbx-sp {{ $statePill[0] }}" style="font-size:9px;padding:1px 6px">{{ $statePill[1] }}</span>
                @endif
              </strong>
              <time class="mbx-rtime" datetime="{{ $msgTime?->toIso8601String() }}">
                {{ $timeDisplay }}
              </time>
            </span>
    
            <div class="mbx-rsub">{{ $subject }}</div>
            <small class="mbx-rpre">{{ $preview }}</small>
    
            @if(!$isOutboxMsg && $email->has_attachments)
              <span class="mbx-rtag">
                <i class="fa-solid fa-paperclip" style="font-size:9px" aria-hidden="true"></i>
                Attachment
              </span>
            @endif
          </span>
    
        </a>
      @empty
        <div class="mbx-empty">
          <div class="mbx-empty-ic">
            <i class="fa-regular {{ $isOutboxFolder ? 'fa-paper-plane' : 'fa-envelope' }}" aria-hidden="true"></i>
          </div>
          <strong>{{ $isOutboxFolder ? 'No sent messages' : 'This folder is empty' }}</strong>
          <span>{{ $isOutboxFolder ? 'Emails you send will appear here.' : 'Synchronize the account or adjust your filters.' }}</span>
        </div>
      @endforelse
    </div>
    
    @if($emails->hasPages())
      <div class="mbx-lpag">{{ $emails->links() }}</div>
    @endif

  </section>{{-- /list --}}


  {{-- ══════════════ READING PANE ══════════════ --}}
  <section class="mbx-reading {{ $selected ? 'visible' : '' }}" aria-label="Email reading pane">

    @if($selected)

      {{-- Subject header — pinned --}}
      <header class="mbx-rh">
        <div class="mbx-rh-copy">
          <div class="mbx-msg-count" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span>
              <i class="fa-regular fa-comments" style="font-size:12px" aria-hidden="true"></i>
              {{ $threadMessages->count() }} {{ str('message')->plural($threadMessages->count()) }}
            </span>
            @if($threadMessages->count() > 1)
              <button type="button"
                      id="mbxThreadExpandAllBtn"
                      onclick="toggleExpandAllThreads()"
                      title="Expand or collapse all messages"
                      style="background:var(--c-surface2); border:1px solid var(--c-border); border-radius:6px; padding:2px 8px; font-size:11px; font-weight:600; color:var(--c-sub); cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all .14s;">
                <i class="fa-solid fa-up-right-and-down-left-from-center" id="mbxExpandIcon" aria-hidden="true" style="font-size:10px;"></i>
                <span id="mbxExpandLabel">Expand all</span>
              </button>

              <button type="button"
                      id="mbxThreadSortBtn"
                      onclick="toggleThreadSortOrder()"
                      title="Toggle thread order (Oldest / Newest)"
                      style="background:var(--c-surface2); border:1px solid var(--c-border); border-radius:6px; padding:2px 8px; font-size:11px; font-weight:600; color:var(--c-sub); cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all .14s;">
                <i class="fa-solid fa-arrow-up-wide-short" id="mbxSortIcon" aria-hidden="true" style="font-size:10px;"></i>
                <span id="mbxSortLabel">Oldest first</span>
              </button>
            @endif
          </div>
          <h2>{{ $selected->subject ?: '(No subject)' }}</h2>
        </div>
        <span class="mbx-rh-pill {{ $selected->is_read ? '' : 'unr' }}">
          {{ $selected->is_read ? 'Read' : 'Unread' }}
        </span>
      </header>

      {{-- ── Thread: ONLY THIS SCROLLS ── --}}
      <div class="mbx-thread" role="list" aria-label="Message thread" data-sort-order="asc">

        @foreach($threadMessages as $msg)
          @php
            $isOut = $msg instanceof \App\Models\MailboxOutboxMessage;

            if ($isOut) {
              $sn  = $msg->account?->name ?? 'Me';
              $se  = $msg->account?->email ?? '';
              $st  = $msg->sent_at ?? $msg->updated_at;
              $to  = collect($msg->to_addresses ?? [])
                       ->map(fn($a) => $a['name'] ?? $a['email'] ?? $a)
                       ->filter()->join(', ');
              $spCls = 'sp-' . ($msg->state ?? 'draft');
              $spLbl = ucfirst($msg->state ?? 'draft');
            } else {
              $tf  = collect($msg->from_addresses ?? [])->first();
              $sn  = $tf['name']  ?? $tf['email'] ?? 'Unknown';
              $se  = $tf['email'] ?? '';
              $st  = $msg->received_at;
            }

            $si  = strtoupper(mb_substr($sn, 0, 2));
            $sc  = $avatarColor($se ?: $sn);

            /* Default: Open ONLY the single latest message ($loop->last); collapse all earlier ones */
            $isExpanded = $loop->last || ($threadMessages->count() === 1);

            /* Sanitise HTML body */
            $sh = null;
            if ($msg->html_body) {
              $sh = preg_replace([
                '/<script\b[^>]*>.*?<\/script>/is',
                '/<link\b[^>]*\brel=["\']?stylesheet["\']?[^>]*>/i',
                '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\')/i',
                '/<base\b[^>]*>/i',
                '/(<\/?)(?:html|head|body)\b([^>]*>)/i',
                '/<\/template>/i',
              ], ['','','','','$1div$2','&lt;/template&gt;'],
              $msg->html_body);
            }
          @endphp

          <article class="mbx-card {{ $msg->id === $selected->id ? 'cur' : '' }} {{ $isExpanded ? 'is-expanded' : 'is-collapsed' }}"
                   role="listitem"
                   data-message-id="{{ $msg->id }}">

            {{-- Sender row (Click header to toggle open/close) --}}
            <div class="mbx-cs" onclick="toggleCardExpand(this.parentElement)">
              <span class="mbx-av" style="background:{{ $sc }}" aria-hidden="true">{{ $si }}</span>
              <div class="mbx-ci">
                <strong>
                  {{ $isOut ? 'Me (' . $se . ')' : $sn }}
                  @if($isOut)
                    <span class="mbx-sp {{ $spCls }}">{{ $spLbl }}</span>
                  @endif
                </strong>
                <small>{{ ($isOut && !empty($to)) ? 'To: ' . $to : $se }}</small>
              </div>
              <time class="mbx-ct" datetime="{{ $st?->toIso8601String() }}">
                {{ $st?->format('d M Y, h:i A') }}
              </time>
              <i class="fa-solid fa-chevron-down mbx-chevron" aria-hidden="true"></i>
            </div>

            {{-- Body --}}
            <div class="mbx-cb">
              @if($sh)
                <template class="b360-email-tpl">{!! $sh !!}</template>
                <div class="b360-email-shadow-host" aria-label="Email content">
                  @if($msg->text_body)
                    <div class="b360-email-fallback">
                      {!! nl2br(e(str($msg->text_body)->limit(3000))) !!}
                    </div>
                  @else
                    <div class="b360-email-fallback" style="white-space:normal">{!! $sh !!}</div>
                  @endif
                </div>
              @elseif($msg->text_body)
                <div class="mbx-plain">{!! nl2br(e($msg->text_body)) !!}</div>
              @else
                <p style="color:var(--c-muted);font-style:italic;font-size:13px;margin:0">
                  No message body.
                </p>
              @endif
            </div>

            {{-- Attachments --}}
            @if($msg->attachments?->isNotEmpty())
              <div class="mbx-ca">
                <strong>Attachments</strong>
                @foreach($msg->attachments as $att)
                  <a href="{{ route('mailbox.attachments.download', $att) }}">
                    <i class="fa-solid fa-paperclip" style="font-size:11px" aria-hidden="true"></i>
                    {{ $att->filename ?? $att->original_filename ?? 'File' }}
                  </a>
                @endforeach
              </div>
            @endif

          </article>
        @endforeach

      </div>{{-- /mbx-thread --}}

      {{-- ── Action footer — ALWAYS VISIBLE ── --}}
      <footer class="mbx-af">

        @if(!($isOutboxFolder ?? false))
          {{-- Mark read/unread --}}
          <form method="POST" action="{{ route('mailbox.external.state', $selected) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="action" value="{{ $selected->is_read ? 'unread' : 'read' }}">
            <button type="submit" class="mbx-btn">
              <i class="fa-regular {{ $selected->is_read ? 'fa-envelope' : 'fa-envelope-open' }}" aria-hidden="true"></i>
              Mark {{ $selected->is_read ? 'unread' : 'read' }}
            </button>
          </form>

          {{-- Star/unstar --}}
          <form method="POST" action="{{ route('mailbox.external.state', $selected) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="action" value="{{ $selected->is_flagged ? 'unstar' : 'star' }}">
            <button type="submit" class="mbx-btn">
              <i class="fa-{{ $selected->is_flagged ? 'solid' : 'regular' }} fa-star"
                 style="{{ $selected->is_flagged ? 'color:var(--c-amber)' : '' }}" aria-hidden="true"></i>
              {{ $selected->is_flagged ? 'Remove star' : 'Star' }}
            </button>
          </form>

          {{-- Archive / Spam --}}
          @foreach(['archive' => ['Archive','fa-box-archive'], 'spam' => ['Spam','fa-shield-halved']] as $act => [$lbl, $icon])
            @if($mailboxAccount->folders->contains('special_use', $act))
              <form method="POST" action="{{ route('mailbox.external.state', $selected) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="{{ $act }}">
                <button type="submit" class="mbx-btn">
                  <i class="fa-solid {{ $icon }}" aria-hidden="true"></i> {{ $lbl }}
                </button>
              </form>
            @endif
          @endforeach

          {{-- Delete Workflow Button (opens modal dialog) --}}
          @php $isTrashView = ($folder?->special_use === 'trash'); @endphp
          <button type="button" class="mbx-btn danger" onclick="openDeleteDialog({{ $isTrashView ? 'true' : 'false' }})">
            <i class="fa-solid {{ $isTrashView ? 'fa-trash-can' : 'fa-trash' }}" aria-hidden="true"></i>
            {{ $isTrashView ? 'Delete permanently' : 'Delete' }}
          </button>

          {{-- Restore --}}
          @if(in_array($selected->folder?->special_use, ['trash','spam'], true)
              && $mailboxAccount->folders->contains('special_use', 'inbox'))
            <form method="POST" action="{{ route('mailbox.external.state', $selected) }}">
              @csrf @method('PATCH')
              <input type="hidden" name="action" value="inbox">
              <button type="submit" class="mbx-btn">
                <i class="fa-solid fa-inbox" aria-hidden="true"></i> Restore
              </button>
            </form>
          @endif
        @endif

        {{-- Reply / Reply all / Forward --}}
        @can('send', $mailboxAccount)
          @if(!($isOutboxFolder ?? false))
            <a class="mbx-btn pri"
               href="{{ route('mailbox.external.show', [$mailboxAccount,
                   'folder'          => $activeFolder,
                   'message'         => $selected->id,
                   'compose'         => 'reply',
                   'compose_message' => $selected->id,
               ]) }}">
              <i class="fa-solid fa-reply" aria-hidden="true"></i> Reply
            </a>
            <a class="mbx-btn"
               href="{{ route('mailbox.external.show', [$mailboxAccount,
                   'folder'          => $activeFolder,
                   'message'         => $selected->id,
                   'compose'         => 'reply_all',
                   'compose_message' => $selected->id,
               ]) }}">
              Reply all
            </a>
            <a class="mbx-btn"
               href="{{ route('mailbox.external.show', [$mailboxAccount,
                   'folder'          => $activeFolder,
                   'message'         => $selected->id,
                   'compose'         => 'forward',
                   'compose_message' => $selected->id,
               ]) }}">
              Forward
            </a>
          @endif
        @endcan

      </footer>

    @else

      <div class="mbx-rempty" role="status">
        <div class="mbx-rempty-ic" aria-hidden="true">
          <i class="fa-regular fa-envelope-open"></i>
        </div>
        <strong>Select a message</strong>
        <span>Choose an email from the list to read the conversation.</span>
      </div>

    @endif

  </section>{{-- /reading --}}

</section>{{-- /mbx-screen --}}

@can('send', $mailboxAccount)
  @if(($composeData['open'] ?? false) || $composeDraft || $errors->any())
    @include('mailbox.external.partials.compose')
  @endif
@endcan

@endsection

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
      const syncContainer = document.getElementById('mbxSyncContainer');
      if (!syncContainer) return;

      const syncUrl = syncContainer.getAttribute('data-sync-url');
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
      const syncDot = document.getElementById('mbxSyncDot');
      const syncCopy = document.getElementById('mbxSyncCopy');
      const syncBtn = document.getElementById('mbxSyncBtn');

      let isSyncing = false;

      async function triggerAutoSync() {
          if (isSyncing || !syncUrl) return;
          isSyncing = true;

          if (syncDot) syncDot.classList.add('is-syncing');
          if (syncBtn) {
              syncBtn.disabled = true;
              syncBtn.innerText = 'Scanning...';
          }

          try {
              const response = await fetch(syncUrl, {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'Accept': 'application/json',
                      'X-CSRF-TOKEN': csrfToken || ''
                  }
              });

              if (response.ok) {
                  const data = await response.json();
                  if (syncCopy && data.last_synced_at) {
                      syncCopy.innerText = data.last_synced_at;
                  }
                  if (data.created && data.created > 0) {
                      // New emails found! Auto-reload so new messages appear in Inbox list immediately
                      window.location.reload();
                  }
              }
          } catch (err) {
              console.error('Mailbox auto-sync error:', err);
          } finally {
              isSyncing = false;
              if (syncDot) syncDot.classList.remove('is-syncing');
              if (syncBtn) {
                  syncBtn.disabled = false;
                  syncBtn.innerText = 'Sync now';
              }
          }
      }

      // Auto-sync every 60 seconds (1 minute)
      const AUTO_SYNC_INTERVAL_MS = 60000 * 5;
      setInterval(triggerAutoSync, AUTO_SYNC_INTERVAL_MS);

      // Initial scan 5 seconds after page load
      setTimeout(triggerAutoSync, 5000);

      // Override manual click to use fast AJAX sync
      const syncForm = document.getElementById('mbxSyncForm');
      if (syncForm) {
          syncForm.addEventListener('submit', function(e) {
              e.preventDefault();
              triggerAutoSync();
          });
      }
  });
</script>
<style>
    @keyframes mbxPulseDot {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.3; transform: scale(1.4); }
        100% { opacity: 1; transform: scale(1); }
    }
    .mbx-sdot.is-syncing {
        background-color: #f58220 !important;
        animation: mbxPulseDot 0.8s infinite ease-in-out !important;
    }
</style>

{{-- ════════════════════════════════════════════════════════
     EMAIL DELETE WORKFLOW CONFIRMATION MODAL
     ════════════════════════════════════════════════════════ --}}
<div id="deleteModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(15,23,42,0.55); backdrop-filter:blur(3px); z-index:99999; align-items:center; justify-content:center;">
  <div style="background:#fff; border-radius:14px; width:92%; max-width:440px; padding:24px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.15); font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;">
      <h3 id="deleteModalTitle" style="font-size:16px; font-weight:700; color:#0F172A; margin:0; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-trash-can" style="color:#DC2626;"></i> Delete Email
      </h3>
      <button type="button" onclick="closeDeleteDialog()" style="background:none; border:none; color:#94A3B8; cursor:pointer; font-size:16px; padding:4px;"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <p id="deleteModalBody" style="font-size:13px; color:#475569; margin:0 0 20px; line-height:1.5;">
      What would you like to do with this email?
    </p>

    {{-- Standard folder options (Move to Trash / Archive) --}}
    <div id="standardDeleteOptions" style="display:flex; flex-direction:column; gap:10px;">
      @if($selected)
        <form id="singleTrashForm" method="POST" action="{{ route('mailbox.external.state', $selected) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="action" value="trash">
          <button type="submit" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#DC2626; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
            <i class="fa-solid fa-trash-can"></i> Move to Trash
          </button>
        </form>

        <form id="singleArchiveForm" method="POST" action="{{ route('mailbox.external.state', $selected) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="action" value="archive">
          <button type="submit" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#F1F5F9; color:#0F172A; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
            <i class="fa-solid fa-box-archive"></i> Archive
          </button>
        </form>
      @endif

      {{-- Bulk action buttons --}}
      <div id="bulkButtonsContainer" style="display:none; flex-direction:column; gap:10px;">
        <button type="button" onclick="submitBulkAction('trash')" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#DC2626; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
          <i class="fa-solid fa-trash-can"></i> Move Selected to Trash
        </button>
        <button type="button" onclick="submitBulkAction('archive')" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#F1F5F9; color:#0F172A; border:1px solid #CBD5E1; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
          <i class="fa-solid fa-box-archive"></i> Archive Selected
        </button>
      </div>
    </div>

    {{-- Trash folder option (Delete Permanently) --}}
    <div id="permanentDeleteOptions" style="display:none; flex-direction:column; gap:10px;">
      @if($selected)
        <form id="singlePermDeleteForm" method="POST" action="{{ route('mailbox.external.state', $selected) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="action" value="delete_permanent">
          <button type="submit" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#DC2626; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
            <i class="fa-solid fa-triangle-exclamation"></i> Permanently Delete
          </button>
        </form>
      @endif

      <div id="bulkPermButtonsContainer" style="display:none;">
        <button type="button" onclick="submitBulkAction('delete_permanent')" style="width:100%; display:flex; align-items:center; justify-content:center; gap:8px; padding:11px 16px; background:#DC2626; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
          <i class="fa-solid fa-triangle-exclamation"></i> Permanently Delete Selected
        </button>
      </div>
    </div>

    <div style="margin-top:16px; text-align:right;">
      <button type="button" onclick="closeDeleteDialog()" style="background:none; border:none; color:#64748B; cursor:pointer; font-size:12.5px; font-weight:500; padding:4px 8px;">Cancel</button>
    </div>
  </div>
</div>

<script>
  let isBulkMode = false;

  function getSelectedEmailIds() {
    return Array.from(document.querySelectorAll('.mbx-email-select-chk:checked')).map(chk => chk.value);
  }

  function onEmailCheckChange() {
    const selectedIds = getSelectedEmailIds();
    const count = selectedIds.length;
    const bar = document.getElementById('bulkActionBar');
    const countLabel = document.getElementById('bulkSelectedCount');
    const selectAllChk = document.getElementById('selectAllEmailsChk');

    if (bar) {
      bar.style.display = count > 0 ? 'flex' : 'none';
    }
    if (countLabel) {
      countLabel.innerText = count + (count === 1 ? ' email selected' : ' emails selected');
    }

    const allChks = document.querySelectorAll('.mbx-email-select-chk');
    if (selectAllChk && allChks.length > 0) {
      selectAllChk.checked = count === allChks.length;
    }
  }

  function toggleSelectAllEmails(masterChk) {
    const isChecked = masterChk.checked;
    document.querySelectorAll('.mbx-email-select-chk').forEach(chk => {
      chk.checked = isChecked;
    });
    onEmailCheckChange();
  }

  function openDeleteDialog(isPermanent) {
    isBulkMode = false;
    showDeleteModalUI(isPermanent, false, 1);
  }

  function openBulkDeleteDialog(isPermanent) {
    const selectedIds = getSelectedEmailIds();
    if (selectedIds.length === 0) return;
    isBulkMode = true;
    showDeleteModalUI(isPermanent, true, selectedIds.length);
  }

  function showDeleteModalUI(isPermanent, isBulk, count) {
    const modal = document.getElementById('deleteModal');
    const title = document.getElementById('deleteModalTitle');
    const body = document.getElementById('deleteModalBody');
    const stdOpts = document.getElementById('standardDeleteOptions');
    const permOpts = document.getElementById('permanentDeleteOptions');

    const singleTrashForm = document.getElementById('singleTrashForm');
    const singleArchiveForm = document.getElementById('singleArchiveForm');
    const bulkBtns = document.getElementById('bulkButtonsContainer');

    const singlePermForm = document.getElementById('singlePermDeleteForm');
    const bulkPermBtns = document.getElementById('bulkPermButtonsContainer');

    if (!modal) return;

    if (isPermanent) {
      title.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="color:#DC2626;"></i> ' + (isBulk ? 'Permanently Delete Selected Emails' : 'Permanently Delete Email');
      body.innerText = isBulk
        ? 'These ' + count + ' emails will be permanently deleted from your mailbox and cannot be recovered. Are you sure you want to proceed?'
        : 'This email will be permanently deleted from your mailbox and cannot be recovered. Are you sure you want to proceed?';
      stdOpts.style.display = 'none';
      permOpts.style.display = 'flex';

      if (singlePermForm) singlePermForm.style.display = isBulk ? 'none' : 'block';
      if (bulkPermBtns) bulkPermBtns.style.display = isBulk ? 'block' : 'none';
    } else {
      title.innerHTML = '<i class="fa-solid fa-trash-can" style="color:#DC2626;"></i> ' + (isBulk ? 'Delete ' + count + ' Selected Emails' : 'Delete Email');
      body.innerText = isBulk
        ? 'What would you like to do with the ' + count + ' selected emails? Select whether to move them to Trash or Archive them.'
        : 'What would you like to do with this email? Select whether to move it to Trash or Archive it.';
      stdOpts.style.display = 'flex';
      permOpts.style.display = 'none';

      if (singleTrashForm) singleTrashForm.style.display = isBulk ? 'none' : 'block';
      if (singleArchiveForm) singleArchiveForm.style.display = isBulk ? 'none' : 'block';
      if (bulkBtns) bulkBtns.style.display = isBulk ? 'flex' : 'none';
    }

    modal.style.display = 'flex';
  }

  function closeDeleteDialog() {
    const modal = document.getElementById('deleteModal');
    if (modal) modal.style.display = 'none';
  }

  function submitBulkAction(action) {
    const selectedIds = getSelectedEmailIds();
    if (selectedIds.length === 0) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("mailbox.accounts.bulk-state", $mailboxAccount) }}';

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    const act = document.createElement('input');
    act.type = 'hidden';
    act.name = 'action';
    act.value = action;
    form.appendChild(act);

    selectedIds.forEach(id => {
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'ids[]';
      idInput.value = id;
      form.appendChild(idInput);
    });

    document.body.appendChild(form);
    form.submit();
  }

    function copyEmailToClipboard(email, event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        if (!email) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(email).then(showCopyToast).catch(() => fallbackCopy(email));
        } else {
            fallbackCopy(email);
        }
    }

    function fallbackCopy(email) {
        const textarea = document.createElement('textarea');
        textarea.value = email;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopyToast();
        } catch (err) {
            console.error('Copy failed:', err);
        } finally {
            document.body.removeChild(textarea);
        }
    }

    function showCopyToast() {
        let toast = document.getElementById('copyToastNotification');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'copyToastNotification';
            toast.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#0F172A; color:#FFFFFF; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 10px 25px -5px rgba(0,0,0,0.2); z-index:999999; display:flex; align-items:center; gap:8px; transition:opacity 0.3s ease, transform 0.3s ease; opacity:0; transform:translateY(12px); font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
            toast.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#10B981; font-size:16px;"></i> <span>Email address copied successfully.</span>';
            document.body.appendChild(toast);
        }

        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateY(0)';
        }, 10);

        clearTimeout(window.copyToastTimer);
        window.copyToastTimer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(12px)';
        }, 2800);
    }

    function toggleCardExpand(cardEl) {
        if (!cardEl) return;
        cardEl.classList.toggle('is-expanded');
        cardEl.classList.toggle('is-collapsed');
    }

    function toggleExpandAllThreads() {
        const threadContainer = document.querySelector('.mbx-thread');
        if (!threadContainer) return;

        const cards = Array.from(threadContainer.querySelectorAll('.mbx-card'));
        if (!cards.length) return;

        const hasCollapsed = cards.some(c => c.classList.contains('is-collapsed'));

        cards.forEach(card => {
            if (hasCollapsed) {
                card.classList.remove('is-collapsed');
                card.classList.add('is-expanded');
            } else {
                card.classList.remove('is-expanded');
                card.classList.add('is-collapsed');
            }
        });

        const label = document.getElementById('mbxExpandLabel');
        if (label) {
            label.innerText = hasCollapsed ? 'Collapse all' : 'Expand all';
        }
    }

    function toggleThreadSortOrder() {
        const threadContainer = document.querySelector('.mbx-thread');
        if (!threadContainer) return;

        const cards = Array.from(threadContainer.querySelectorAll('.mbx-card'));
        if (cards.length < 2) return;

        const isAsc = threadContainer.getAttribute('data-sort-order') !== 'desc';
        const newSort = isAsc ? 'desc' : 'asc';
        threadContainer.setAttribute('data-sort-order', newSort);

        cards.reverse().forEach(card => threadContainer.appendChild(card));

        const icon = document.getElementById('mbxSortIcon');
        const label = document.getElementById('mbxSortLabel');

        if (icon) {
            icon.className = newSort === 'asc' ? 'fa-solid fa-arrow-up-wide-short' : 'fa-solid fa-arrow-down-short-wide';
        }
        if (label) {
            label.innerText = newSort === 'asc' ? 'Oldest first' : 'Newest first';
        }
    }
  </script>
@endpush
