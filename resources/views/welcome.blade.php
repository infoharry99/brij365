@extends('layouts.builder360-auth')

@section('title', 'Builder360 ERP CRM')

@section('content')
    <main style="min-height:100vh;display:grid;place-items:center;padding:24px">
        <section class="card" style="width:min(100%,560px);padding:30px">
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px">
                <div class="sb-logo">B</div>
                <div>
                    <h1 style="margin:0;font-size:26px">Builder360 Workspace</h1>
                    <p style="margin:5px 0 0;color:var(--muted)">ERP and CRM operations in one secured workspace.</p>
                </div>
            </div>
            <a class="btn btn-primary" href="{{ auth()->check() ? route('builder360.dashboard') : route('login') }}">
                {{ auth()->check() ? 'Open Builder360' : 'Login' }}
            </a>
        </section>
    </main>
@endsection
