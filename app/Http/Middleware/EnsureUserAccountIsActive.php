<?php

namespace App\Http\Middleware;

use App\Services\Auth\SecurityAuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserAccountIsActive
{
    public function __construct(private readonly SecurityAuditService $securityAudit)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== 'active') {
            $this->securityAudit->inactiveSessionRevoked($user, $request);

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This account is not active. Please contact your Builder360 administrator.',
                ], 403);
            }

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This account is not active. Please contact your Builder360 administrator.',
                ]);
        }

        return $next($request);
    }
}
