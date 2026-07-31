@extends('layouts.builder360-classic')

@section('title', 'Company Administration - Builder360 ERP-CRM')

@section('content')
    @php
        $activeCount = $companies->getCollection()->where('status', 'active')->count();
        $projectCount = $companies->getCollection()->sum('projects_count');
        $userCount = $companies->getCollection()->sum('users_count');
    @endphp

    <div class="blade-workspace" aria-labelledby="company-admin-title">
        <header class="blade-workspace-header">
            <div>
                <p class="blade-dashboard-eyebrow">Administration</p>
                <h1 id="company-admin-title">Company Administration</h1>
                <p>Create companies and review their branches, projects, users and operating status.</p>
            </div>
            <nav class="blade-workspace-actions" aria-label="Admin navigation">
                <a href="{{ url('/') }}">Dashboard</a>
                <a href="{{ route('admin.users.index') }}">Users</a>
                <a href="{{ route('admin.roles.index') }}">Roles</a>
                <a href="{{ route('settings.data-imports.index') }}">Data Imports</a>
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

        <section class="blade-dashboard-kpis" aria-label="Company KPIs">
            <article class="blade-dashboard-kpi"><span>Companies</span><strong>{{ number_format($companies->total()) }}</strong><small>Company register</small></article>
            <article class="blade-dashboard-kpi"><span>Active</span><strong>{{ number_format($activeCount) }}</strong><small>Current page</small></article>
            <article class="blade-dashboard-kpi"><span>Projects</span><strong>{{ number_format($projectCount) }}</strong><small>Current page</small></article>
            <article class="blade-dashboard-kpi"><span>Users</span><strong>{{ number_format($userCount) }}</strong><small>Current page</small></article>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div><span class="blade-dashboard-label">Create</span><h2>Add company</h2></div>
                <small>Required fields are marked</small>
            </div>
            <form method="POST" action="{{ route('admin.companies.store') }}" class="blade-form-grid">
                @csrf
                <label>Company code<input type="text" name="code" value="{{ old('code') }}" maxlength="24" placeholder="B360X" required></label>
                <label>Company name<input type="text" name="name" value="{{ old('name') }}" maxlength="255" required></label>
                <label>Legal name<input type="text" name="legal_name" value="{{ old('legal_name') }}" maxlength="255"></label>
                <label>State code<input type="text" name="state" value="{{ old('state') }}" maxlength="8" placeholder="MH" required></label>
                <label>Status<select name="status"><option value="active" @selected(old('status', 'active') === 'active')>Active</option><option value="inactive" @selected(old('status') === 'inactive')>Inactive</option></select></label>
                <button type="submit" class="blade-primary-action">Create company</button>
            </form>
        </section>

        <section class="blade-dashboard-card">
            <div class="blade-dashboard-section-title">
                <div><span class="blade-dashboard-label">Companies</span><h2>Company register</h2></div>
                <small>{{ $companies->firstItem() ?? 0 }}-{{ $companies->lastItem() ?? 0 }} of {{ $companies->total() }}</small>
            </div>
            <div class="blade-dashboard-table-wrap">
                <table class="blade-dashboard-table">
                    <thead><tr><th scope="col">Company</th><th scope="col">State</th><th scope="col">Branches</th><th scope="col">Projects</th><th scope="col">Users</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td><strong>{{ $company->code }}</strong><span>{{ $company->name }}</span><small>{{ $company->legal_name ?: 'Legal name not recorded' }}</small></td>
                                <td>{{ $company->state }}</td>
                                <td>{{ number_format($company->branches_count) }}</td>
                                <td>{{ number_format($company->projects_count) }}</td>
                                <td>{{ number_format($company->users_count) }}</td>
                                <td><span class="blade-status-pill">{{ ucfirst($company->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No companies are available.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $companies->links() }}
        </section>
    </div>
@endsection
