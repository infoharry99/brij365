@extends('layouts.builder360-classic')

@section('title', 'Builder360 ERP CRM')

@section('content')
    <section class="blade-dashboard-card">
        <div class="blade-dashboard-section-title">
            <div>
                <span class="blade-dashboard-label">Builder360 ERP CRM</span>
                <h1>Builder360 Workspace</h1>
            </div>
        </div>
        <p class="b360-muted">The approved Builder360 workspace is available from the main dashboard.</p>
        <a class="blade-primary-action" href="{{ route('builder360.dashboard') }}">Open dashboard</a>
    </section>
@endsection
