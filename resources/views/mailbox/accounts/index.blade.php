@extends('layouts.builder360-classic')

@section('title', 'Company Email Accounts | Builder360')

@push('styles')
<style>
  .b360-content {
        padding: 6px !important;
    }
    /* ═══════════════════════════════════════════════
    MAILBOX ACCOUNTS — ARCTIC CLARITY
    White + Light Blue · System fonts · CSP-safe
    ═══════════════════════════════════════════════ */
    :root {
    --ac-bg:          #F0F4FA;
    --ac-surface:     #FFFFFF;
    --ac-surface-2:   #F7F9FC;
    --ac-border:      #E2E8F2;
    --ac-border-2:    #C7D5EA;
    --ac-blue-50:     #EEF4FF;
    --ac-blue-100:    #DBEAFE;
    --ac-blue-200:    #BFDBFE;
    --ac-blue-500:    #3B82F6;
    --ac-blue-600:    #F5852B;
    --ac-blue-700:    #1D4ED8;
    --ac-accent:      #F5852B;
    --ac-accent-soft: #EEF4FF;
    --ac-text:        #0F172A;
    --ac-text-2:      #334155;
    --ac-text-3:      #64748B;
    --ac-text-muted:  #94A3B8;
    --ac-success:     #10B981;
    --ac-warning:     #F59E0B;
    --ac-danger:      #EF4444;
    --ac-shadow-sm:   0 2px 8px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
    --ac-shadow-md:   0 4px 16px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
    --r-sm: 8px; --r-md: 12px; --r-lg: 16px;
    --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .mac-wrap * { box-sizing: border-box; margin: 0; padding: 0; }
    .mac-wrap { font-family: var(--font); -webkit-font-smoothing: antialiased; color: var(--ac-text); }

    /* ── Page shell ── */
    .mac-wrap {
    background: var(--ac-bg);
    min-height: 100vh;
    padding: 32px 28px 48px;
    }

    /* ── Page heading ── */
    .mac-page-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 28px;
    flex-wrap: wrap;
    }
    .mac-page-head-copy { display: flex; flex-direction: column; gap: 4px; }
    .mac-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    color: var(--ac-accent);
    margin-bottom: 2px;
    }
    .mac-eyebrow::before {
    content: '';
    display: inline-block; width: 14px; height: 2px;
    background: var(--ac-accent); border-radius: 2px;
    }
    .mac-page-head h1 {
    font-size: 22px; font-weight: 700;
    color: var(--ac-text); line-height: 1.25;
    }
    .mac-page-head p {
    font-size: 13.5px; color: var(--ac-text-muted); line-height: 1.5; margin-top: 2px;
    }

    /* ── Buttons ── */
    .mac-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 18px; border-radius: var(--r-md);
    font-size: 13px; font-weight: 600; font-family: var(--font);
    text-decoration: none; cursor: pointer;
    border: 1px solid var(--ac-border-2);
    background: var(--ac-surface); color: var(--ac-text-2);
    transition: all .15s; white-space: nowrap;
    }
    .mac-btn:hover { background: var(--ac-blue-50); border-color: var(--ac-blue-200); color: var(--ac-accent); text-decoration: none; }
    .mac-btn-primary {
    background: var(--ac-accent); color: #fff;
    border-color: var(--ac-accent);
    box-shadow: 0 2px 8px rgba(37,99,235,0.22);
    }
    .mac-btn-primary:hover { background: var(--ac-blue-700); border-color: var(--ac-blue-700); color: #fff; box-shadow: 0 4px 14px rgba(37,99,235,0.30); text-decoration: none; }
    .mac-btn-danger { color: var(--ac-danger); border-color: #FCA5A5; background: #FFF5F5; }
    .mac-btn-danger:hover { background: #FEE2E2; border-color: #F87171; color: var(--ac-danger); }

    /* ── Two-column grid ── */
    .mac-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    align-items: start;
    }
    @media (max-width: 960px) { .mac-grid { grid-template-columns: 1fr; } }

    /* ── Panel ── */
    .mac-panel {
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--r-lg);
    overflow: hidden;
    box-shadow: var(--ac-shadow-sm);
    }
    .mac-panel-top-line {
    height: 3px;
    background: linear-gradient(90deg, var(--ac-blue-600), #60a5fa);
    }
    .mac-panel-head {
    padding: 20px 22px 16px;
    border-bottom: 1px solid var(--ac-border);
    background: var(--ac-surface-2);
    }
    .mac-panel-head h2 { font-size: 14.5px; font-weight: 700; color: var(--ac-text); margin-bottom: 2px; }
    .mac-panel-head p  { font-size: 12.5px; color: var(--ac-text-muted); line-height: 1.45; }

    /* ── Account list ── */
    .mac-acct-list { display: flex; flex-direction: column; }

    .mac-acct-card {
    padding: 16px 22px;
    border-bottom: 1px solid var(--ac-border);
    transition: background .13s;
    }
    .mac-acct-card:last-child { border-bottom: none; }
    .mac-acct-card:hover { background: var(--ac-surface-2); }

    .mac-acct-row {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    }

    /* Status dot */
    .mac-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--ac-border-2); flex-shrink: 0;
    }
    .mac-dot.is-active { background: var(--ac-success); box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    .mac-acct-info { flex: 1; min-width: 0; }
    .mac-acct-info strong { display: block; font-size: 14px; font-weight: 600; color: var(--ac-text); }
    .mac-acct-info small  { display: block; font-size: 12px; color: var(--ac-text-muted); margin-top: 1px; }
    .mac-acct-info span   { display: block; font-size: 11.5px; color: var(--ac-text-3); margin-top: 2px; }

    /* Status pill */
    .mac-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 20px;
    font-size: 11px; font-weight: 600;
    background: var(--ac-blue-50); color: var(--ac-blue-600);
    border: 1px solid var(--ac-blue-200);
    white-space: nowrap; flex-shrink: 0;
    }
    .mac-pill.is-active { background: rgba(16,185,129,.1); color: #059669; border-color: rgba(16,185,129,.25); }
    .mac-pill::before { content: ''; display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

    /* Manage access accordion */
    .mac-access-details {
    margin-top: 12px;
    border: 1px solid var(--ac-border);
    border-radius: var(--r-md);
    overflow: hidden;
    }
    .mac-access-details summary {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 14px;
    cursor: pointer; list-style: none;
    font-size: 12.5px; font-weight: 600; color: var(--ac-accent);
    background: var(--ac-blue-50);
    transition: background .13s;
    user-select: none;
    }
    .mac-access-details summary::-webkit-details-marker { display: none; }
    .mac-access-details summary:hover { background: var(--ac-blue-100); }
    .mac-access-details summary i { font-size: 10px; transition: transform .2s; margin-left: auto; color: var(--ac-text-muted); }
    .mac-access-details[open] summary i { transform: rotate(180deg); }
    .mac-access-details[open] summary { border-bottom: 1px solid var(--ac-border); }

    .mac-access-body { padding: 16px 18px; background: var(--ac-surface); }

    /* ── Form grid ── */
    .mac-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 16px;
    }
    .mac-form-grid label {
    display: flex; flex-direction: column; gap: 5px;
    font-size: 12px; font-weight: 600; color: var(--ac-text-2);
    }
    .mac-form-wide { grid-column: 1 / -1; }

    .mac-form-grid input,
    .mac-form-grid select,
    .mac-form-grid textarea {
    height: 36px;
    padding: 0 12px;
    background: var(--ac-surface);
    border: 1px solid var(--ac-border);
    border-radius: var(--r-sm);
    font-size: 13px; color: var(--ac-text);
    font-family: var(--font); outline: none;
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
    }
    .mac-form-grid textarea { height: auto; padding: 10px 12px; resize: vertical; }
    .mac-form-grid input:focus,
    .mac-form-grid select:focus,
    .mac-form-grid textarea:focus {
    border-color: var(--ac-blue-500);
    box-shadow: 0 0 0 3px rgba(37,99,235,0.10);
    background: var(--ac-surface);
    }
    .mac-form-grid input::placeholder,
    .mac-form-grid textarea::placeholder { color: var(--ac-text-muted); }
    .mac-form-grid small { font-size: 11px; color: var(--ac-text-muted); font-weight: 400; margin-top: 2px; }

    /* Checkbox row */
    .mac-checkbox-row {
    display: flex !important; flex-direction: row !important;
    align-items: center; gap: 8px;
    font-size: 13px !important; font-weight: 500 !important;
    color: var(--ac-text-2) !important; cursor: pointer;
    padding: 4px 0;
    }
    .mac-checkbox-row input[type="checkbox"] {
    width: 15px; height: 15px;
    accent-color: var(--ac-accent);
    flex-shrink: 0; cursor: pointer;
    /* override height from generic input rule */
    height: 15px !important; padding: 0 !important;
    }

    /* Form actions row */
    .mac-form-actions {
    display: flex; align-items: center; gap: 8px;
    padding-top: 4px;
    }

    /* Assign form — inside accordion */
    .mac-assign-form { margin-bottom: 16px; }
    .mac-assign-form .mac-form-grid { grid-template-columns: 1fr 1fr; }

    /* Assignee list */
    .mac-assignee-list {
    display: flex; flex-direction: column; gap: 6px;
    padding-top: 12px;
    border-top: 1px solid var(--ac-border);
    margin-top: 4px;
    }
    .mac-assignee-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 10px; padding: 8px 12px;
    background: var(--ac-surface-2);
    border: 1px solid var(--ac-border);
    border-radius: var(--r-sm);
    }
    .mac-assignee-row strong { display: block; font-size: 13px; font-weight: 600; color: var(--ac-text); }
    .mac-assignee-row small  { display: block; font-size: 11.5px; color: var(--ac-text-muted); margin-top: 1px; }

    /* Owner badge */
    .mac-owner-badge {
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
    padding: 3px 9px; border-radius: 20px;
    background: var(--ac-blue-50); color: var(--ac-blue-600);
    border: 1px solid var(--ac-blue-200);
    flex-shrink: 0;
    }

    /* Empty state */
    .mac-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 40px 24px; text-align: center;
    }
    .mac-empty-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: var(--ac-blue-50);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: var(--ac-blue-500); margin-bottom: 4px;
    }
    .mac-empty strong { font-size: 14px; font-weight: 700; color: var(--ac-text); }
    .mac-empty span   { font-size: 13px; color: var(--ac-text-muted); max-width: 240px; line-height: 1.5; }

    /* Section divider label */
    .mac-section-sep {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 22px 10px;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    color: var(--ac-text-muted);
    border-bottom: 1px solid var(--ac-border);
    }
</style>
@endpush

@section('content')
<div class="mac-wrap">

  {{-- ── Page heading ── --}}
  <header class="mac-page-head">
    <div class="mac-page-head-copy">
      <span class="mac-eyebrow">Mailbox</span>
      <h1>Company email accounts</h1>
      <p>Open an assigned company mailbox or connect an IMAP and SMTP account.</p>
    </div>
    @if($accounts->isNotEmpty())
      <a class="mac-btn" href="{{ route('mailbox.index') }}">
        <i class="fa-solid fa-inbox" style="font-size:12px"></i>
        Open mailbox
      </a>
    @endif
  </header>

  <div class="mac-grid">

    {{-- ══════════════════════════
         AVAILABLE ACCOUNTS PANEL
         ══════════════════════════ --}}
    <section class="mac-panel">
      <div class="mac-panel-top-line"></div>
      <header class="mac-panel-head">
        <h2>Available accounts</h2>
        <p>You only see company mailboxes assigned to your account.</p>
      </header>

      <div class="mac-acct-list">
        @forelse($accounts as $account)

          <article class="mac-acct-card">
            {{-- Account summary row --}}
            <div class="mac-acct-row">
              <span class="mac-dot {{ $account->status === 'active' ? 'is-active' : '' }}"></span>
              <img src="{{ route('mailbox.accounts.avatar', $account) }}" alt="{{ $account->name }}" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid var(--ac-border-2); flex-shrink:0;">
              <div class="mac-acct-info">
                <strong>{{ $account->name }}</strong>
                <small style="display:inline-flex; align-items:center; gap:4px;">
                  {{ $account->email }}
                  <button type="button" onclick="copyEmailToClipboard('{{ $account->email }}', event)" title="Copy email address" style="background:none; border:none; color:#64748B; cursor:pointer; padding:1px 4px; font-size:11px; border-radius:3px;" onmouseover="this.style.color='#F58220'" onmouseout="this.style.color='#64748B'">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                  </button>
                </small>
                <span>{{ $account->emails_count }} messages &middot; {{ $account->unread_count }} unread</span>
              </div>
              <span class="mac-pill {{ $account->status === 'active' ? 'is-active' : '' }}">
                {{ str($account->status)->headline() }}
              </span>
              <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="mac-btn" onclick="toggleAccountPanel('details-{{ $account->id }}')">
                  <i class="fa-solid fa-eye" style="font-size:11px"></i>
                  Show
                </button>
                @can('update', $account)
                  <button type="button" class="mac-btn" onclick="toggleAccountPanel('edit-{{ $account->id }}')">
                    <i class="fa-solid fa-pen-to-square" style="font-size:11px"></i>
                    Edit
                  </button>
                  <button type="button" class="mac-btn" onclick="toggleAccountPanel('password-{{ $account->id }}')">
                    <i class="fa-solid fa-key" style="font-size:11px"></i>
                    Change Password
                  </button>
                @endcan
                <a class="mac-btn mac-btn-primary" href="{{ route('mailbox.external.show', $account) }}">
                  <i class="fa-solid fa-arrow-right" style="font-size:11px"></i>
                  Open
                </a>
              </div>
            </div>

            {{-- 1. SHOW DETAILS PANEL --}}
            <div id="details-{{ $account->id }}" class="mac-sub-panel" style="display:none; margin-top:14px; padding:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px;">
              <h3 style="font-size:13px; font-weight:700; color:#0F172A; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                <span><i class="fa-solid fa-circle-info" style="color:var(--ac-accent); margin-right:6px;"></i> Email Account Details</span>
                <button type="button" onclick="toggleAccountPanel('details-{{ $account->id }}')" style="background:none; border:none; color:#94A3B8; cursor:pointer; font-size:14px;"><i class="fa-solid fa-xmark"></i></button>
              </h3>
              <div style="display:flex; align-items:center; gap:14px; background:#fff; padding:12px 14px; border:1px solid #E2E8F0; border-radius:8px; margin-bottom:12px;">
                <img src="{{ route('mailbox.accounts.avatar', $account) }}" alt="{{ $account->name }}" style="width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid var(--ac-accent); flex-shrink:0;">
                <div>
                  <h4 style="font-size:14px; font-weight:700; color:#0F172A; margin:0;">{{ $account->name }}</h4>
                  <p style="font-size:12.5px; color:#64748B; margin:2px 0 0; display:inline-flex; align-items:center; gap:4px;">
                    {{ $account->email }}
                    <button type="button" onclick="copyEmailToClipboard('{{ $account->email }}', event)" title="Copy email address" style="background:none; border:none; color:#64748B; cursor:pointer; padding:1px 4px; font-size:11px; border-radius:3px;" onmouseover="this.style.color='#F58220'" onmouseout="this.style.color='#64748B'">
                      <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    </button>
                  </p>
                </div>
              </div>
              <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; font-size:12.5px;">
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Account Name</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">{{ $account->name }}</div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Email Address</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px; display:inline-flex; align-items:center; gap:4px;">
                    {{ $account->email }}
                    <button type="button" onclick="copyEmailToClipboard('{{ $account->email }}', event)" title="Copy email address" style="background:none; border:none; color:#64748B; cursor:pointer; padding:1px 4px; font-size:11px; border-radius:3px;" onmouseover="this.style.color='#F58220'" onmouseout="this.style.color='#64748B'">
                      <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    </button>
                  </div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Username</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">{{ $account->username }}</div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Incoming Server (IMAP)</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">{{ $account->imap_host }}</div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Outgoing Server (SMTP)</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">{{ $account->smtp_host }}</div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Ports</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">IMAP: {{ $account->imap_port }} &middot; SMTP: {{ $account->smtp_port }}</div>
                </div>
                <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0;">
                  <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Encryption</span>
                  <div style="font-weight:600; color:#0F172A; margin-top:2px;">IMAP: {{ strtoupper($account->imap_encryption ?: 'None') }} &middot; SMTP: {{ strtoupper($account->smtp_encryption ?: 'None') }}</div>
                </div>
              </div>
              <div style="background:#fff; padding:10px 14px; border-radius:6px; border:1px solid #E2E8F0; margin-top:12px;">
                <span style="font-size:10.5px; color:#64748B; font-weight:700; text-transform:uppercase; letter-spacing:.05em;">Signature</span>
                <div style="font-size:12.5px; color:#334155; margin-top:4px; white-space:pre-wrap;">{{ ($account->settings['signature_text'] ?? null) ?: 'No signature configured.' }}</div>
              </div>
            </div>

            {{-- 2. EDIT ACCOUNT PANEL --}}
            @can('update', $account)
              <div id="edit-{{ $account->id }}" class="mac-sub-panel" style="display:none; margin-top:14px; padding:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px;">
                <h3 style="font-size:13px; font-weight:700; color:#0F172A; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                  <span><i class="fa-solid fa-pen-to-square" style="color:var(--ac-accent); margin-right:6px;"></i> Edit Email Account</span>
                  <button type="button" onclick="toggleAccountPanel('edit-{{ $account->id }}')" style="background:none; border:none; color:#94A3B8; cursor:pointer; font-size:14px;"><i class="fa-solid fa-xmark"></i></button>
                </h3>
                <form method="POST" action="{{ route('mailbox.accounts.update', $account) }}" enctype="multipart/form-data" class="mac-form-grid">
                  @csrf
                  @method('PUT')

                  {{-- Account Profile Photo / Avatar --}}
                  <div class="mac-form-wide" style="display:flex; align-items:center; gap:14px; background:#fff; padding:12px 14px; border:1px solid #E2E8F0; border-radius:8px;">
                    <img src="{{ route('mailbox.accounts.avatar', $account) }}" alt="{{ $account->name }}" style="width:52px; height:52px; border-radius:50%; object-fit:cover; border:2px solid var(--ac-accent); flex-shrink:0;">
                    <div style="flex:1;">
                      <label style="font-size:12px; font-weight:600; color:#0F172A; margin:0; display:block;">
                        Account Profile Photo / Avatar
                        <input type="file" name="avatar" accept="image/*" style="margin-top:4px; font-size:12px;">
                      </label>
                      @if(! empty($account->settings['avatar_path']))
                        <label class="mac-checkbox-row" style="margin-top:6px;">
                          <input type="checkbox" name="remove_avatar" value="1">
                          Remove profile picture
                        </label>
                      @endif
                    </div>
                  </div>

                  {{-- Editable Fields --}}
                  <label class="mac-form-wide">
                    Email Account Name <span style="color:#EF4444">*</span>
                    <input name="name" required value="{{ old('name', $account->name) }}" placeholder="Sales Mailbox">
                  </label>
                  <label class="mac-form-wide">
                    Email Password (Optional)
                    <input type="password" name="secret" autocomplete="new-password" placeholder="Leave blank to keep existing password">
                    <small>Enter a new password only if you want to update it here.</small>
                  </label>
                  <label class="mac-form-wide">
                    Email Signature (Optional)
                    <textarea name="signature" rows="4" maxlength="5000" placeholder="Name&#10;Title&#10;Company">{{ old('signature', $account->settings['signature_text'] ?? '') }}</textarea>
                  </label>

                  <div class="mac-form-wide" style="border-top:1px solid #E2E8F0; padding-top:10px; margin-top:4px; font-size:10.5px; font-weight:700; color:#64748B; text-transform:uppercase; letter-spacing:.05em;">
                    Read-Only Account Settings
                  </div>

                  <label>
                    Email Address
                    <input value="{{ $account->email }}" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>
                  <label>
                    Username
                    <input value="{{ $account->username }}" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>
                  <label>
                    Incoming Server (IMAP)
                    <input value="{{ $account->imap_host }}" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>
                  <label>
                    IMAP Port & Encryption
                    <input value="{{ $account->imap_port }} ({{ strtoupper($account->imap_encryption ?: 'None') }})" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>
                  <label>
                    Outgoing Server (SMTP)
                    <input value="{{ $account->smtp_host }}" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>
                  <label>
                    SMTP Port & Encryption
                    <input value="{{ $account->smtp_port }} ({{ strtoupper($account->smtp_encryption ?: 'None') }})" readonly disabled style="background:#E2E8F0; color:#64748B; cursor:not-allowed;">
                  </label>

                  <div class="mac-form-actions mac-form-wide" style="padding-top:8px;">
                    <button type="submit" class="mac-btn mac-btn-primary">
                      <i class="fa-solid fa-check" style="font-size:11px"></i> Save Account
                    </button>
                    <button type="button" class="mac-btn" onclick="toggleAccountPanel('edit-{{ $account->id }}')">Cancel</button>
                  </div>
                </form>
              </div>

              {{-- 3. CHANGE PASSWORD PANEL --}}
              <div id="password-{{ $account->id }}" class="mac-sub-panel" style="display:none; margin-top:14px; padding:16px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px;">
                <h3 style="font-size:13px; font-weight:700; color:#0F172A; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                  <span><i class="fa-solid fa-key" style="color:var(--ac-accent); margin-right:6px;"></i> Change Password</span>
                  <button type="button" onclick="toggleAccountPanel('password-{{ $account->id }}')" style="background:none; border:none; color:#94A3B8; cursor:pointer; font-size:14px;"><i class="fa-solid fa-xmark"></i></button>
                </h3>
                <form method="POST" action="{{ route('mailbox.accounts.change-password', $account) }}" class="mac-form-grid">
                  @csrf
                  @method('PATCH')

                  <label class="mac-form-wide">
                    New Email Password <span style="color:#EF4444">*</span>
                    <input type="password" name="secret" required placeholder="Enter new email account password" autocomplete="new-password">
                    <small>Updates only the stored email password without affecting any other account settings.</small>
                  </label>

                  <div class="mac-form-actions mac-form-wide" style="padding-top:8px;">
                    <button type="submit" class="mac-btn mac-btn-primary">
                      <i class="fa-solid fa-key" style="font-size:11px"></i> Update Password
                    </button>
                    <button type="button" class="mac-btn" onclick="toggleAccountPanel('password-{{ $account->id }}')">Cancel</button>
                  </div>
                </form>
              </div>
            @endcan

            {{-- Manage access accordion --}}
            @can('update', $account)
              <details class="mac-access-details">
                <summary>
                  <i class="fa-solid fa-users" style="font-size:11px; margin-right:2px; color:var(--ac-accent)"></i>
                  Manage access
                  <i class="fa-solid fa-chevron-down"></i>
                </summary>
                <div class="mac-access-body">

                  {{-- Assign form --}}
                  <div class="mac-assign-form">
                    <form method="POST"
                          action="{{ route('mailbox.accounts.assignments.store', $account) }}"
                          class="mac-form-grid">
                      @csrf
                      <label class="mac-form-wide">
                        Employee
                        <select name="user_id" required>
                          <option value="">Select employee</option>
                          @foreach($assignableUsers as $person)
                            <option value="{{ $person->id }}">
                              {{ $person->name }} &middot; {{ $person->role?->name ?? 'Employee' }}
                            </option>
                          @endforeach
                        </select>
                      </label>

                      <label class="mac-checkbox-row">
                        <input type="hidden" name="can_view" value="0">
                        <input type="checkbox" name="can_view" value="1" checked>
                        View
                      </label>
                      <label class="mac-checkbox-row">
                        <input type="hidden" name="can_send" value="0">
                        <input type="checkbox" name="can_send" value="1">
                        Send
                      </label>
                      <label class="mac-checkbox-row">
                        <input type="hidden" name="can_manage" value="0">
                        <input type="checkbox" name="can_manage" value="1">
                        Manage account
                      </label>
                      <label class="mac-checkbox-row">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1">
                        Default account
                      </label>

                      <div class="mac-form-actions mac-form-wide">
                        <button type="submit" class="mac-btn mac-btn-primary">
                          <i class="fa-solid fa-user-plus" style="font-size:11px"></i>
                          Assign access
                        </button>
                      </div>
                    </form>
                  </div>

                  {{-- Current assignees --}}
                  @if($account->assignments->isNotEmpty())
                    <div class="mac-assignee-list">
                      @foreach($account->assignments as $assignment)
                        <div class="mac-assignee-row">
                          <div>
                            <strong>{{ $assignment->user?->name }}</strong>
                            <small>
                              {{ collect([
                                   $assignment->can_view    ? 'View'    : null,
                                   $assignment->can_send    ? 'Send'    : null,
                                   $assignment->can_manage  ? 'Manage'  : null,
                                   $assignment->is_default  ? 'Default' : null,
                                 ])->filter()->join(' · ') }}
                            </small>
                          </div>
                          @if($assignment->user_id !== $account->user_id)
                            <form method="POST"
                                  action="{{ route('mailbox.accounts.assignments.destroy', [$account, $assignment]) }}"
                                  onsubmit="return confirm('Remove this mailbox assignment?')">
                              @csrf @method('DELETE')
                              <button type="submit" class="mac-btn mac-btn-danger" style="padding:5px 12px;font-size:12px">
                                <i class="fa-solid fa-trash" style="font-size:10px"></i>
                                Remove
                              </button>
                            </form>
                          @else
                            <span class="mac-owner-badge">Owner</span>
                          @endif
                        </div>
                      @endforeach
                    </div>
                  @endif

                </div>
              </details>
            @endcan
          </article>

        @empty
          <div class="mac-empty">
            <div class="mac-empty-icon"><i class="fa-regular fa-envelope"></i></div>
            <strong>Connect company email account</strong>
            <span>An assigned IMAP and SMTP account is required before Mailbox can send or receive email.</span>
          </div>
        @endforelse
      </div>
    </section>

    {{-- ══════════════════════════
         CONNECT ACCOUNT PANEL
         ══════════════════════════ --}}
    @can('create', App\Models\MailboxAccount::class)
      <section class="mac-panel">
        <div class="mac-panel-top-line"></div>
        <header class="mac-panel-head">
          <h2>Connect account</h2>
          <p>Use a company mailbox and an app password when the provider requires multi-factor authentication.</p>
        </header>

        <div style="padding:20px 22px 24px">
          <form method="POST" action="{{ route('mailbox.accounts.store') }}" enctype="multipart/form-data" class="mac-form-grid">
            @csrf

            {{-- Identity --}}
            <label>
              Account name
              <input name="name" required maxlength="120"
                     value="{{ old('name') }}" placeholder="Sales mailbox">
            </label>
            <label>
              Email address
              <input type="email" name="email" required
                     value="{{ old('email') }}" placeholder="sales@company.com">
            </label>
            <label class="mac-form-wide">
              Account Profile Photo / Avatar (Optional)
              <input type="file" name="avatar" accept="image/*">
            </label>

            {{-- IMAP --}}
            <div class="mac-form-wide" style="border-top:1px solid var(--ac-border);padding-top:14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ac-text-muted)">
              IMAP (incoming)
            </div>
            <label>
              IMAP host
              <input name="imap_host" required value="{{ old('imap_host') }}" placeholder="imap.example.com">
            </label>
            <label>
              IMAP port
              <input type="number" name="imap_port" required min="1" max="65535" value="{{ old('imap_port', 993) }}">
            </label>
            <label>
              IMAP security
              <select name="imap_encryption">
                <option value="ssl">SSL</option>
                <option value="tls">TLS</option>
                <option value="">None</option>
              </select>
            </label>
            <label class="mac-checkbox-row" style="align-self:flex-end;padding-bottom:6px">
              <input type="hidden" name="imap_validate_cert" value="0">
              <input type="checkbox" name="imap_validate_cert" value="1" checked>
              Verify server certificate
            </label>

            {{-- SMTP --}}
            <div class="mac-form-wide" style="border-top:1px solid var(--ac-border);padding-top:14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ac-text-muted)">
              SMTP (outgoing)
            </div>
            <label>
              SMTP host
              <input name="smtp_host" required value="{{ old('smtp_host') }}" placeholder="smtp.example.com">
            </label>
            <label>
              SMTP port
              <input type="number" name="smtp_port" required min="1" max="65535" value="{{ old('smtp_port', 587) }}">
            </label>
            <label>
              SMTP security
              <select name="smtp_encryption">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="">None</option>
              </select>
            </label>

            {{-- Credentials --}}
            <div class="mac-form-wide" style="border-top:1px solid var(--ac-border);padding-top:14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ac-text-muted)">
              Credentials
            </div>
            <label>
              Username
              <input name="username" required autocomplete="username" value="{{ old('username') }}">
            </label>
            <label>
              Sync interval
              <select name="sync_interval_minutes">
                <option value="5">Every 5 minutes</option>
                <option value="10">Every 10 minutes</option>
                <option value="15">Every 15 minutes</option>
                <option value="30">Every 30 minutes</option>
              </select>
            </label>
            <label class="mac-form-wide">
              Password or app password
              <input type="password" name="secret" required autocomplete="new-password">
              <small>The connection is tested before the account is saved.</small>
            </label>

            {{-- Signature --}}
            <div class="mac-form-wide" style="border-top:1px solid var(--ac-border);padding-top:14px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ac-text-muted)">
              Email signature
            </div>
            <label class="mac-form-wide">
              Signature (optional)
              <textarea name="signature" rows="4" maxlength="5000"
                        placeholder="Name&#10;Title&#10;Company">{{ old('signature') }}</textarea>
            </label>

            {{-- Submit --}}
            <div class="mac-form-actions mac-form-wide" style="padding-top:4px">
              <button type="submit" class="mac-btn mac-btn-primary">
                <i class="fa-solid fa-plug" style="font-size:11px"></i>
                Test and connect
              </button>
            </div>

          </form>
        </div>
      </section>

    @else
      <section class="mac-panel">
        <div class="mac-panel-top-line"></div>
        <div class="mac-empty" style="padding:60px 24px">
          <div class="mac-empty-icon"><i class="fa-solid fa-lock"></i></div>
          <strong>No mailbox assigned</strong>
          <span>Ask your administrator to assign a company email account to you.</span>
        </div>
      </section>
    @endcan

  </div>{{-- /mac-grid --}}
</div>{{-- /mac-wrap --}}
@endsection

@push('scripts')
<script>
function toggleAccountPanel(panelId) {
    const panel = document.getElementById(panelId);
    if (!panel) return;
    const card = panel.closest('.mac-acct-card');
    const isOpen = panel.style.display !== 'none';
    if (card) {
        card.querySelectorAll('.mac-sub-panel').forEach(p => {
            if (p.id !== panelId) p.style.display = 'none';
        });
    }
    panel.style.display = isOpen ? 'none' : 'block';
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
</script>
@endpush
