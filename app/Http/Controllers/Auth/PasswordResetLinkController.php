<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const GENERIC_STATUS = 'If the email belongs to an active Builder360 account, a secure password reset link will be sent.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, SecurityAuditService $securityAudit): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        RateLimiter::hit($request->throttleKey(), 60);

        $data = $request->validated();
        $email = Str::lower(trim((string) $data['email']));

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if (! $user) {
            $securityAudit->passwordResetRequested(null, $request, $email, 'no_active_account');

            return back()->with('status', self::GENERIC_STATUS);
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            $securityAudit->passwordResetRequested($user, $request, $user->email, 'throttled');

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'A reset link was already requested recently. Please wait before trying again.']);
        }

        $securityAudit->passwordResetRequested($user, $request, $user->email, 'sent');

        return back()->with('status', self::GENERIC_STATUS);
    }
}
