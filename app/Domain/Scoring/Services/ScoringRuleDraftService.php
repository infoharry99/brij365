<?php

namespace App\Domain\Scoring\Services;

use App\Application\Scoring\DTOs\CreateScoringRuleData;
use App\Application\Scoring\DTOs\UpdateScoringRuleData;
use App\Models\ScoringRule;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ScoringRuleDraftService
{
    public function __construct(
        private readonly ScoringRuleCatalog $catalog,
        private readonly CompanyScopeService $companyScope,
        private readonly AuditLogger $audit,
        private readonly ScoringConfigurationChecksum $checksum,
    ) {
    }

    public function create(CreateScoringRuleData $data, User $actor, ?Request $request = null): ScoringRule
    {
        return DB::transaction(function () use ($data, $actor, $request): ScoringRule {
            $companyId = $this->companyScope->companyIdFor($actor);
            if ($companyId === null || $companyId <= 0) {
                throw ValidationException::withMessages(['company_id' => 'An active company is required to create a scoring rule.']);
            }

            $previous = ScoringRule::query()
                ->where('company_id', $companyId)
                ->where('rule_key', $data->ruleKey)
                ->latest('version')
                ->lockForUpdate()
                ->first();

            $configuration = $this->catalog->defaultConfiguration($data->ruleKey);
            $checksum = $this->checksum->make($configuration);

            $rule = ScoringRule::create([
                'company_id' => $companyId,
                'previous_rule_id' => $previous?->id,
                'created_by_user_id' => $actor->id,
                'rule_key' => $data->ruleKey,
                'name' => $data->name,
                'version' => ($previous?->version ?? 0) + 1,
                'status' => 'draft',
                'configuration' => $configuration,
                'configuration_checksum' => $checksum,
                'change_reason' => $data->changeReason,
                'effective_at' => $data->effectiveAt,
                'metadata' => ['source' => 'scoring_logic_workspace'],
            ]);

            $rule = $this->synchronizePersistedChecksum($rule);

            $this->audit->record($actor, 'scoring.rule.draft_created', 'Created scoring rule draft', $rule, [
                'rule_key' => $rule->rule_key,
                'version' => $rule->version,
                'checksum' => $rule->configuration_checksum,
            ], $request);

            return $rule->load(['createdBy', 'previousRule']);
        });
    }

    public function update(ScoringRule $rule, UpdateScoringRuleData $data, User $actor, ?Request $request = null): ScoringRule
    {
        return DB::transaction(function () use ($rule, $data, $actor, $request): ScoringRule {
            $locked = ScoringRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['draft', 'validated', 'rejected'], true)) {
                throw ValidationException::withMessages(['rule' => 'Only draft, validated or rejected rules can be edited.']);
            }

            $locked->forceFill([
                'name' => $data->name,
                'change_reason' => $data->changeReason,
                'effective_at' => $data->effectiveAt,
                'configuration' => $data->configuration,
                'configuration_checksum' => $this->checksum->make($data->configuration),
                'status' => 'draft',
            ])->save();

            $locked = $this->synchronizePersistedChecksum($locked);

            $this->audit->record($actor, 'scoring.rule.draft_updated', 'Updated scoring rule draft', $locked, [
                'rule_key' => $locked->rule_key,
                'version' => $locked->version,
                'checksum' => $locked->configuration_checksum,
            ], $request);

            return $locked->fresh(['createdBy', 'previousRule']);
        });
    }

    public function clone(ScoringRule $source, string $reason, User $actor, ?Request $request = null, bool $rollback = false): ScoringRule
    {
        return DB::transaction(function () use ($source, $reason, $actor, $request, $rollback): ScoringRule {
            $latest = ScoringRule::query()
                ->where('company_id', $source->company_id)
                ->where('rule_key', $source->rule_key)
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();

            $rule = ScoringRule::create([
                'company_id' => $source->company_id,
                'previous_rule_id' => $latest->id,
                'created_by_user_id' => $actor->id,
                'rule_key' => $source->rule_key,
                'name' => $source->name,
                'version' => $latest->version + 1,
                'status' => 'draft',
                'configuration' => $source->configuration,
                'configuration_checksum' => $this->checksum->make($source->configuration ?? []),
                'change_reason' => $reason,
                'metadata' => [
                    $rollback ? 'rollback_source_rule_id' : 'cloned_from_rule_id' => $source->id,
                    'source_version' => $source->version,
                ],
            ]);

            $event = $rollback ? 'scoring.rule.rollback_draft_created' : 'scoring.rule.cloned';
            $rule = $this->synchronizePersistedChecksum($rule);

            $this->audit->record($actor, $event, $rollback ? 'Created scoring rollback draft' : 'Cloned scoring rule', $rule, [
                'source_rule_id' => $source->id,
                'source_version' => $source->version,
                'new_version' => $rule->version,
            ], $request);

            return $rule->load(['createdBy', 'previousRule']);
        });
    }

    /**
     * Eloquent JSON casts may normalize numeric scalar types during persistence. The
     * governed checksum must therefore be derived from the stored representation,
     * not only from the pre-persistence request payload.
     */
    private function synchronizePersistedChecksum(ScoringRule $rule): ScoringRule
    {
        $persisted = $rule->fresh();
        $checksum = $this->checksum->make($persisted->configuration ?? []);

        if (! hash_equals((string) $persisted->configuration_checksum, $checksum)) {
            $persisted->forceFill(['configuration_checksum' => $checksum])->save();
        }

        return $persisted;
    }
}
