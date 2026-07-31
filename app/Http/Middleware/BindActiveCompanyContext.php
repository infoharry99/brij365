<?php

namespace App\Http\Middleware;

use App\Services\Security\CompanyScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BindActiveCompanyContext
{
    public function __construct(private readonly CompanyScopeService $companyScope)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || ! (bool) config('builder360.single_company.enabled', true)) {
            return $next($request);
        }

        $companyId = $this->companyScope->companyIdFor($user);

        if (! $request->filled('company_id') && $companyId !== null && $companyId > 0) {
            $request->merge(['company_id' => $companyId]);
        }

        return $next($request);
    }
}
