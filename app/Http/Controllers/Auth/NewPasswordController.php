<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function store(ResetPasswordRequest $request, SecurityAuditService $securityAudit): RedirectResponse
    {
        $data = $request->validated();
        $email = Str::lower(trim((string) $data['email']));

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        $status = Password::reset(
            [
                'email' => $user->email,
                'password' => $data['password'],
                'password_confirmation' => $request->string('password_confirmation')->toString(),
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
        }

        $securityAudit->passwordResetCompleted($user->fresh(), $request);

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. You can sign in with the new password.');
    }
}
