@extends('layouts.builder360-auth')

@section('title', 'Verify Email — Builder360 ERP CRM')

@section('content')
<main style="min-height:100vh;display:grid;place-items:center;background:var(--bg);padding:24px">
        <section class="card" style="width:min(100%,500px);padding:28px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
                <div class="sb-logo" style="position:static">B</div>
                <div>
                    <h1 style="margin:0;font-size:24px">Verify Your Email</h1>
                    <p style="margin:4px 0 0;color:var(--muted)">Confirm your email before accessing Builder360 ERP–CRM modules.</p>
                </div>
            </div>

            @if (session('status'))
                <div role="status" style="margin-bottom:16px;padding:12px;border:1px solid var(--green);border-radius:12px;background:rgba(34,197,94,.08);color:var(--green);font-size:13px">
                    {{ session('status') }}
                </div>
            @endif

            <div style="margin-bottom:18px;padding:14px;border:1px solid var(--line);border-radius:12px;background:var(--surface-2);font-size:14px;color:var(--muted);line-height:1.5">
                We sent a verification link to <strong style="color:var(--text)">{{ auth()->user()->email }}</strong>.
                If you cannot find it, request a fresh link below. In this local workspace, email is written through the configured mailer.
            </div>

            <form method="POST" action="{{ route('verification.send') }}" style="margin-bottom:12px">
                @csrf
                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">
                    Resend verification link
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn" type="submit" style="width:100%;justify-content:center">
                    Log out
                </button>
            </form>
        </section>
    </main>
@endsection
