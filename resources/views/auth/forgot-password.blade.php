@extends('layouts.builder360-auth')

@section('title', 'Forgot Password — Builder360 ERP CRM')

@section('content')
<main style="min-height:100vh;display:grid;place-items:center;background:var(--bg);padding:24px">
        <section class="card" style="width:min(100%,460px);padding:28px">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:22px">
                <div class="sb-logo" style="position:static">B</div>
                <div>
                    <h1 style="margin:0;font-size:24px">Reset Password</h1>
                    <p style="margin:4px 0 0;color:var(--muted)">Request a secure reset link for an active Builder360 account.</p>
                </div>
            </div>

            @if (session('status'))
                <div role="status" style="margin-bottom:16px;padding:12px;border:1px solid var(--green);border-radius:12px;background:rgba(34,197,94,.08);color:var(--green);font-size:13px">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <label style="display:block;margin-bottom:14px">
                    <span style="display:block;font-weight:700;margin-bottom:6px">Work Email</span>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        style="width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:var(--surface);color:var(--text)"
                    >
                    @error('email')
                        <span style="display:block;margin-top:6px;color:var(--red);font-size:13px">{{ $message }}</span>
                    @enderror
                </label>

                <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">
                    Send reset link
                </button>
            </form>

            <div style="margin-top:16px;text-align:center">
                <a href="{{ route('login') }}" style="color:var(--accent);font-weight:700;text-decoration:none">
                    Back to login
                </a>
            </div>

            <div style="margin-top:18px;padding:12px;border:1px solid var(--line);border-radius:12px;background:var(--surface-2);font-size:13px;color:var(--muted)">
                For security, this page shows the same confirmation message even if the email is not registered.
            </div>
        </section>
    </main>
@endsection
