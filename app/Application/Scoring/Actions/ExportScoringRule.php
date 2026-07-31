<?php

namespace App\Application\Scoring\Actions;

use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;

final class ExportScoringRule
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function handle(ScoringRule $rule, User $user, Request $request): array
    {
        $this->audit->record($user, 'scoring.rule.exported', 'Exported scoring rule version', $rule, [
            'rule_key' => $rule->rule_key, 'version' => $rule->version, 'checksum' => $rule->configuration_checksum,
        ], $request);

        return [
            'rule_key' => $rule->rule_key, 'name' => $rule->name, 'version' => $rule->version,
            'status' => $rule->status, 'configuration' => $rule->configuration,
            'configuration_checksum' => $rule->configuration_checksum, 'change_reason' => $rule->change_reason,
            'effective_at' => $rule->effective_at?->toIso8601String(), 'activated_at' => $rule->activated_at?->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
        ];
    }
}
