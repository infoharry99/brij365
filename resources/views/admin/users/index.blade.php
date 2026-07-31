@extends('layouts.builder360-classic')

@section('title', 'User Administration - Builder360 ERP-CRM')

@section('content')
@php
        $activeCount = $users->getCollection()->where('status', 'active')->count();
        $suspendedCount = $users->getCollection()->where('status', 'suspended')->count();
        $companyCount = $users->getCollection()->pluck('company_id')->filter()->unique()->count();
    @endphp

    <div class="blade-workspace" aria-labelledby="user-admin-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="user-admin-title">User Administration</h1>
                <p>
                    Workspace for user creation, company access,
                    role assignment, account status control and activity history.
                </p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Admin navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('admin.companies.index') }}">Companies</a>
                <a href="{{ route('admin.roles.index') }}">Roles</a>
                <a href="{{ route('settings.data-imports.index') }}">Data Imports</a>
                <a href="{{ route('governance.audit-events.index') }}">Activity History</a>
                <a href="{{ route('admin.users.index') }}">Reset filters</a>
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

        <section class="blade-dashboard-kpis" aria-label="User KPIs">
            <article class="blade-dashboard-kpi">
                <span>Total Users</span>
                <strong>{{ number_format($users->total()) }}</strong>
                <small>User register</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Active</span>
                <strong>{{ number_format($activeCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Suspended</span>
                <strong>{{ number_format($suspendedCount) }}</strong>
                <small>Current page</small>
            </article>
            <article class="blade-dashboard-kpi">
                <span>Companies</span>
                <strong>{{ number_format($companyCount) }}</strong>
                <small>Current page</small>
            </article>
        </section>

        <section class="blade-workspace-grid">
            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Create</span>
                        <h2>Create user account</h2>
                    </div>
                    <small>{{ $canCreateUser ? 'Authorized' : 'Read only' }}</small>
                </div>

                @if ($canCreateUser)
                    <form method="POST" action="{{ route('admin.users.store') }}" class="blade-form-grid">
                        @csrf
                        <x-forms.company-context :companies="$companies" required />
                        <label>
                            Role
                            <select name="role_id" required>
                                <option value="">Select role</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}" @selected((int) old('role_id') === (int) $role->id)>{{ $role->name }} · {{ $role->scope_level }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            Name
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                        </label>
                        <label>
                            Email
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required>
                        </label>
                        <label>
                            Password
                            <input type="password" name="password" required autocomplete="new-password">
                            <small>Strong password policy is enforced server-side.</small>
                        </label>
                        <label>
                            Status
                            <select name="status">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="blade-primary-action">Create user</button>
                    </form>
                @else
                    <p class="blade-muted">This role can view users but cannot create accounts.</p>
                @endif
            </article>

            <article class="blade-dashboard-card">
                <div class="blade-dashboard-section-title">
                    <div>
                        <span class="blade-dashboard-label">Controls</span>
                        <h2>User filters</h2>
                    </div>
                    <small>{{ number_format($users->total()) }} record(s)</small>
                </div>

                <form method="GET" action="{{ route('admin.users.index') }}" class="blade-filter-grid blade-filter-grid-compact">
                    <x-forms.company-context :companies="$companies" :selected="$filters['company_id'] ?? null" placeholder="All companies" />
                    <label>
                        Role
                        <select name="role_id">
                            <option value="">All roles</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected(($filters['role_id'] ?? null) == $role->id)>{{ $role->name }}</option>
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
                        Search
                        <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email">
                    </label>
                    <button type="submit" class="blade-secondary-action">Apply filters</button>
                </form>
            </article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div>
                    <span class="blade-dashboard-label">Access Control</span>
                    <h2>User register</h2>
                </div>
                <small>{{ $users->firstItem() ?? 0 }}-{{ $users->lastItem() ?? 0 }} of {{ $users->total() }}</small>
            </div>

            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">Company</th>
                            <th scope="col">Role</th>
                            <th scope="col">Employee</th>
                            <th scope="col">Status</th>
                            <th scope="col">Access update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            <tr>
                                <td>
                                    <strong>{{ $managedUser->name }}</strong>
                                    <span>{{ $managedUser->email }}</span>
                                </td>
                                <td>
                                    <span>{{ $managedUser->company?->code ?? 'No company' }}</span>
                                    <small>{{ $managedUser->company?->name }}</small>
                                </td>
                                <td>
                                    <span>{{ $managedUser->role?->name ?? 'No role' }}</span>
                                    <small>{{ $managedUser->role?->scope_level }}</small>
                                </td>
                                <td>
                                    @if ($managedUser->employee)
                                        <span>{{ $managedUser->employee->employee_code }}</span>
                                        <small>{{ $managedUser->employee->department }} · {{ $managedUser->employee->designation }}</small>
                                    @else
                                        <span class="blade-muted">No linked employee</span>
                                    @endif
                                </td>
                                <td><span class="blade-status-pill">{{ $statuses[$managedUser->status] ?? $managedUser->status }}</span></td>
                                <td>
                                    @can('updateAccess', $managedUser)
                                        <form method="POST" action="{{ route('admin.users.access.update', $managedUser) }}" class="blade-form-grid">
                                            @csrf
                                            @method('PATCH')
                                            <x-forms.company-context :companies="$companies" :selected="$managedUser->company_id" required />
                                            <label>
                                                Role
                                                <select name="role_id" required>
                                                    @foreach ($roles as $role)
                                                        <option value="{{ $role->id }}" @selected((int) $managedUser->role_id === (int) $role->id)>{{ $role->name }} · {{ $role->scope_level }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>
                                                Status
                                                <select name="status" required>
                                                    @foreach ($statuses as $value => $label)
                                                        <option value="{{ $value }}" @selected($managedUser->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <button type="submit" class="blade-secondary-action">Update access</button>
                                        </form>
                                    @else
                                        <span class="blade-muted">No update access</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No users found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $users->links() }}
        </section>
    </div>
@endsection
