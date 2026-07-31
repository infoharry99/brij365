<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ReadinessCheckRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Operations\HealthCheckService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function health(): JsonResponse
    {
        return response()->json($this->healthCheckService->liveness());
    }

    public function readiness(ReadinessCheckRequest $request): JsonResponse
    {
        $payload = $this->healthCheckService->readiness();

        $this->auditLogger->record(
            $request->user(),
            'operations.readiness.viewed',
            'Viewed operational readiness checks',
            null,
            [
                'status' => $payload['status'],
                'environment' => $payload['environment'],
                'check_statuses' => collect($payload['checks'])
                    ->map(fn (array $check): ?string => $check['status'] ?? null)
                    ->all(),
            ],
            $request,
        );

        return response()->json($payload, $payload['status'] === 'ok' ? 200 : 503);
    }
}
