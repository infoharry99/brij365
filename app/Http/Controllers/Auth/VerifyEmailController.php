<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request, SecurityAuditService $securityAudit): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('collaboration.tasks.index', absolute: false));
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
            $securityAudit->emailVerified($request->user(), $request);
        }

        return redirect()
            ->intended(route('collaboration.tasks.index', absolute: false))
            ->with('status', 'Your email address has been verified.');
    }
}
