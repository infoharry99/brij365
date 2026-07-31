@extends('layouts.builder360-classic')

@section('title', 'Role Administration - Builder360 ERP-CRM')

@section('content')
@php
        $activeCount = $roles->getCollection()->where('is_active', true)->count();
        $globalCount = $roles->getCollection()->where('scope_level', 'global')->count();
        $wildcardCount = $roles->getCollection()->filter(fn ($role) => in_array('*', $role->permissions ?? [], true))->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="role-admin-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="role-admin-title">Role Administration</h1>
                <p>
                    Workspace for role creation, permission assignment,
                    access control, active/inactive status and role change history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Admin navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <a href="{{ route('governance.audit-events.index') }}">Activity History</a>
                <a href="{{ route('admin.roles.index') }}">Reset filters</a>
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

        <section class="blade-dashboard-kpis" aria-label="Role KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Roles</span>
                <strong>{{ number_format($roles->total()) }}</strong>
                <small>Role register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Active</span>
                <strong>{{ number_format($activeCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Global Scope</span>
                <strong>{{ number_format($globalCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Wildcard</span>
                <strong>{{ number_format($wildcardCount) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Create role</h2>
                    </div>
                    <small>{{ $canCreateRole ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateRole)
                    <form method="POST" action="{{ route('admin.roles.store') }}" class="blade-form-grid">
                        @csrf
                        <label>
                            Slug
                            <input type="text" name="slug" value="{{ old('slug') }}" maxlength="80" placeholder="site_ops_viewer" required>
                        </label>
                        <label>
                            Name
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="120" required>
                        </label>
                        <label>
                            Scope level
                            <select name="scope_level" required>
                                @foreach ($scopeLevels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('scope_level', 'company') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Status
                            <select name="is_active">
                                <option value="1" @selected(old('is_active', '1') === '1')>Active</option>
                                <option value="0" @selected(old('is_active') === '0')>Inactive</option>
                            </select>
                        </label>
                        <label class="blade-form-wide">
                            Permissions
                            <select name="permissions[]" multiple required>
                                @foreach ($permissions as $permission)
                                    <option value="{{ $permission }}" @selected(in_array($permission, old('permissions', []), true))>{{ $permission }}</option>
                                @endforeach
                            </select>
                            <small>Use Ctrl/Cmd to select multiple permissions. Non-global admins can grant only their own permissions.</small>
                        </label>
                        <button type="submit" class="blade-primary-action">Create role</button>
                    </form>
                @else
                    <p class="blade-muted">This role can view roles but cannot create new access profiles.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>Role filters</h2>
                    </div>
                    <small>{{ number_format($roles->total()) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('admin.roles.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <label>
                        Scope
                        <select name="scope_level">
                            <option value="">All scopes</option>
                            @foreach ($scopeLevels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['scope_level'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Status
                        <select name="is_active">
                            <option value="">All statuses</option>
                            <option value="1" @selected((string) ($filters['is_active'] ?? '') === '1')>Active</option>
                            <option value="0" @selected((string) ($filters['is_active'] ?? '') === '0')>Inactive</option>
                        </select>
                    </label>
                    <label>
                        Search
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Slug or name">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Management</span>
                    <h2>Role register</h2>
                </div>
                <small>{{ $roles->firstItem() ?? 0 }}-{{ $roles->lastItem() ?? 0 }} of {{ $roles->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">Role</th>
                            <th scope="col">Scope</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Users</th>
                            <th scope="col">Status</th>
                            <th scope="col">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>
                                    <strong>{{ $role->name }}</strong>
                                    <span>{{ $role->slug }}</span>
                                </td>
                                <td>{{ $scopeLevels[$role->scope_level] ?? $role->scope_level }}</td>
                                <td>
                                    <span>{{ count($role->permissions ?? []) }} permission(s)</span>
                                    <small>{{ \Illuminate\Support\Str::limit(implode(', ', $role->permissions ?? []), 120) }}</small>
                                </td>
                                <td>{{ number_format((int) ($role->users_count ?? 0)) }}</td>
                                <td><span class="blade-status-pill">{{ $role->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>
                                    @can('update', $role)
                                        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="blade-form-grid">
                                            @csrf
                                            @method('PATCH')
                                            <label>
                                                Name
                                                <input type="text" name="name" value="{{ old('name', $role->name) }}" maxlength="120" required>
                                            </label>
                                            <label>
                                                Scope
                                                <select name="scope_level" required>
                                                    @foreach ($scopeLevels as $value => $label)
                                                        <option value="{{ $value }}" @selected($role->scope_level === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>
                                                Status
                                                <select name="is_active" required>
                                                    <option value="1" @selected($role->is_active)>Active</option>
                                                    <option value="0" @selected(! $role->is_active)>Inactive</option>
                                                </select>
                                            </label>
                                            <label class="blade-form-wide">
                                                Permissions
                                                <select name="permissions[]" multiple required>
                                                    @foreach ($permissions as $permission)
                                                        <option value="{{ $permission }}" @selected(in_array($permission, $role->permissions ?? [], true))>{{ $permission }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <button type="submit" class="blade-secondary-action">Update role</button>
                                        </form>
                                    @else
                                        <span class="blade-muted">No update access</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No roles found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $roles->links() }}
        </section>
    </div>
@endsection
