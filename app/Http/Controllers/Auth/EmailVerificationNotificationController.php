<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendEmailVerificationNotificationRequest;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Http\RedirectResponse;

class EmailVerificationNotificationController extends Controller
{
    public function store(SendEmailVerificationNotificationRequest $request, SecurityAuditService $securityAudit): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('collaboration.tasks.index', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();
        $securityAudit->emailVerificationNotificationSent($request->user(), $request);

        return back()->with('status', 'A fresh verification link has been sent to your email address.');
    }
}
