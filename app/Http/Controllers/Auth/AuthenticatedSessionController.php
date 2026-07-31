<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        app(SecurityAuditService::class)->loginSucceeded($request->user(), $request);

        return redirect()->intended(route('collaboration.tasks.index', absolute: false));
    }

    public function destroy(LogoutRequest $request, SecurityAuditService $securityAudit): RedirectResponse
    {
        $securityAudit->logout($request->user(), $request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
