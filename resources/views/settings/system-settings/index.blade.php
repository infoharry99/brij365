@extends('layouts.builder360-classic')

@section('title', 'System Settings - Builder360 ERP-CRM')

@section('content')
@php
        $draftCount = $settings->getCollection()->where('status', 'draft')->count();
        $activeCount = $settings->getCollection()->where('status', 'active')->count();
        $archivedCount = $settings->getCollection()->where('status', 'archived')->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="system-settings-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="system-settings-title">System Settings</h1>
                <p>
                    Workspace for configurable business rules, policy versions,
                    approval-controlled activation, effective dates and settings change history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Settings navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('admin.roles.index') }}">Roles</a>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <a href="{{ route('governance.audit-events.index') }}">Activity History</a>
                <a href="{{ route('settings.system-settings.index') }}">Reset filters</a>
            </nav>
        </header>

        @if (session('status'))
            <div class="blade-alert blade-alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="blade-alert blade-alert-danger">
                <strong>Check the highlighted inputs.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="blade-dashboard-kpis" aria-label="Settings KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Settings</span>
                <strong>{{ number_format($settings->total()) }}</strong>
                <small>Settings register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Draft</span>
                <strong>{{ number_format($draftCount) }}</strong>
                <small>Needs approval</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Active</span>
                <strong>{{ number_format($activeCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Archived</span>
                <strong>{{ number_format($archivedCount) }}</strong>
                <small>Historical versions</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Draft</span>
                        <h2>Create setting draft</h2>
                    </div>
                    <small>{{ $canCreateSetting ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateSetting)
                    <form
                        method="POST"
                        action="{{ route('settings.system-settings.store') }}"
                        class="blade-form-grid"
                        x-data="serverFormState"
                        x-on:submit="beginSubmit"
                        x-bind:aria-busy="busyAria"
                        data-idle-label="Create draft"
                        data-busy-label="Creating draft…"
                    >
                        @csrf
                        <x-forms.company-context :companies="$companies" label="Company access" placeholder="Actor company default" />
                        <label>
                            Group
                            <input type="text" name="setting_group" value="{{ old('setting_group') }}" maxlength="80" placeholder="after_sales" required>
                        </label>
                        <label>
                            Key
                            <input type="text" name="setting_key" value="{{ old('setting_key') }}" maxlength="160" placeholder="after_sales.sla_hours" required>
                        </label>
                        <label>
                            Label
                            <input type="text" name="label" value="{{ old('label') }}" maxlength="255" required>
                        </label>
                        <label>
                            Value type
                            <select name="value_type" required>
                                @foreach ($valueTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('value_type', 'object') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Effective from
                            <input type="date" name="effective_from" value="{{ old('effective_from') }}">
                        </label>
                        <label class="blade-form-wide">
                            Description
                            <textarea name="description" rows="3">{{ old('description') }}</textarea>
                        </label>
                        <label class="blade-form-wide">
                            Value
                            <textarea name="value" rows="8" required>{{ old('value', "{\n  \"value\": true\n}") }}</textarea>
                            <small>For JSON/object/array value types, enter valid JSON. Special settings such as lead scoring and task templates are deeply validated.</small>
                        </label>
                        <button type="submit" class="blade-primary-action" x-bind:disabled="busy"><span x-text="submitLabel">Create draft</span></button>
                    </form>
                @else
                    <p class="blade-muted">This role can view settings but cannot create configuration drafts.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Setting filters</h2>
                    </div>
                    <small>{{ number_format($settings->total()) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('settings.system-settings.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Group
                        <select name="setting_group">
                            <option value="">All groups</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group }}" @selected(($filters['setting_group'] ?? null) === $group)>{{ $group }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Key
                        <select name="setting_key">
                            <option value="">All keys</option>
                            @foreach ($keys as $key)
                                <option value="{{ $key }}" @selected(($filters['setting_key'] ?? null) === $key)>{{ $key }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Status
                        <select name="status">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Scope key
                        <input type="text" name="scope_key" value="{{ $filters['scope_key'] ?? '' }}" placeholder="global or company:1">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Configuration</span>
                    <h2>Setting register</h2>
                </div>
                <small>{{ $settings->firstItem() ?? 0 }}-{{ $settings->lastItem() ?? 0 }} of {{ $settings->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Setting</th>
                            <th scope="col">Scope</th>
                            <th scope="col">Version</th>
                            <th scope="col">Effective</th>
                            <th scope="col">Value</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settings as $setting)
                            <tr>
                                <td>
                                    <strong>{{ $setting->label }}</strong>
                                    <span>{{ $setting->setting_key }}</span>
                                    <small>{{ $setting->setting_group }}</small>
                                </td>
                                <td>
                                    <span>{{ $setting->scope_key }}</span>
                                    <small>{{ $setting->company?->name ?? 'Global' }}</small>
                                </td>
                                <td>
                                    <span>v{{ $setting->version }}</span>
                                    <small>{{ $setting->value_type }}</small>
                                </td>
                                <td>
                                    <span>{{ $setting->effective_from?->format('d M Y') ?? 'Immediate' }}</span>
                                    @if ($setting->effective_to)
                                        <small>to {{ $setting->effective_to->format('d M Y') }}</small>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ \Illuminate\Support\Str::limit(json_encode($setting->value, JSON_UNESCAPED_SLASHES), 140) }}</small>
                                </td>
                                <td><span class="blade-status-pill">{{ $statuses[$setting->status] ?? $setting->status }}</span></td>
                                <td>
                                    @can('approve', $setting)
                                        <form
                                            method="POST"
                                            action="{{ route('settings.system-settings.approve', $setting) }}"
                                            class="blade-inline-form"
                                            x-data="serverFormState"
                                            x-on:submit="beginSubmit"
                                            x-bind:aria-busy="busyAria"
                                            data-idle-label="Approve"
                                            data-busy-label="Approving…"
                                        >
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="note" placeholder="Approval note" maxlength="1000">
                                            <button type="submit" class="blade-primary-action" x-bind:disabled="busy"><span x-text="submitLabel">Approve</span></button>
                                        </form>
                                    @else
                                        <span class="blade-muted">No approval action</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">No settings found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $settings->links() }}
        </section>
    </div>
@endsection
