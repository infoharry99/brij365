<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Auth\SecurityAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthApiController extends Controller
{
    public function __construct(private readonly SecurityAuditService $securityAudit)
    {
    }

    /**
     * POST /api/auth/login
     * Authenticate user and return Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($data['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->securityAudit->loginFailed($request, $data['email'], 'rate_limited');

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $credentials = [
            'email'    => $data['email'],
            'password' => $data['password'],
            'status'   => 'active',
        ];

        if (! Auth::attempt($credentials)) {
            RateLimiter::hit($throttleKey);
            $this->securityAudit->loginFailed($request, $data['email']);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->securityAudit->loginSucceeded($user, $request);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->userPayload($user),
        ]);
    }

    /**
     * POST /api/auth/logout
     * Revoke the current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $this->securityAudit->logout($user, $request);

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * GET /api/auth/me
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user()->load('role', 'company', 'employee');

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    /**
     * Build a consistent user payload for API responses.
     *
     * @param \App\Models\User $user
     * @return array<string, mixed>
     */
    private function userPayload(\App\Models\User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'status'            => $user->status,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'role'              => $user->relationLoaded('role') && $user->role ? [
                'id'   => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug ?? null,
            ] : null,
            'company'           => $user->relationLoaded('company') && $user->company ? [
                'id'   => $user->company->id,
                'name' => $user->company->name,
                'code' => $user->company->code ?? null,
            ] : null,
            'profile_photo_url' => $user->profile_photo_path
                ? asset('storage/'.$user->profile_photo_path)
                : null,
            'created_at'        => $user->created_at?->toISOString(),
        ];
    }
}
