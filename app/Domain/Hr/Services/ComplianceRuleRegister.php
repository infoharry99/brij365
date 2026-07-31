<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\ComplianceRuleRowData;
use App\Application\Hr\Data\ComplianceRuleSummaryData;
use App\Domain\Payroll\Services\CanonicalPayrollHasher;
use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Http\Requests\Hr\ComplianceRuleSettingIndexRequest;
use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ComplianceRuleRegister
{
    public function __construct(
        private readonly CompanyScopeService $scope,
        private readonly PaginationPolicy $pagination,
        private readonly CanonicalPayrollHasher $hasher,
        private readonly StatutoryRulePackDefinitionValidator $statutoryValidator,
    ) {}

    public function all(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->settingsScope($actor, $filters)
            ->with([
                'company:id,code,name',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
                'statutoryVerification.verifiedBy:id,name,email',
            ])
            ->orderByRaw("case when status = 'draft' then 0 when status = 'active' then 1 else 2 end")
            ->orderBy('setting_key')->orderByDesc('version')
            ->paginate($this->pagination->workspacePerPage($filters['per_page'] ?? null));
    }

    public function summary(User $actor, array $filters): ComplianceRuleSummaryData
    {
        $summaryFilters = $filters;
        unset($summaryFilters['status'], $summaryFilters['page'], $summaryFilters['per_page']);
        $query = $this->settingsScope($actor, $summaryFilters);
        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $verificationRequired = (clone $query)
            ->with('statutoryVerification')
            ->get(['id', 'created_by_user_id', 'value'])
            ->filter(fn (SystemSetting $setting): bool => $this->isGoverned($setting)
                && ! $this->hasCurrentIndependentVerification($setting))
            ->count();

        return new ComplianceRuleSummaryData(
            total: (int) $counts->sum(),
            draft: (int) $counts->get('draft', 0),
            active: (int) $counts->get('active', 0),
            archived: (int) $counts->get('archived', 0),
            verificationRequired: $verificationRequired,
        );
    }

    public function present(User $actor, LengthAwarePaginator $settings, array $settingKeys): LengthAwarePaginator
    {
        return $settings->through(function (SystemSetting $setting) use ($actor, $settingKeys): ComplianceRuleRowData {
            $governed = $this->isGoverned($setting);
            $verified = $governed
                ? $this->hasCurrentIndependentVerification($setting)
                : (bool) data_get($setting->value, 'verified', false);
            $source = $this->officialSource((array) $setting->value);

            return new ComplianceRuleRowData(
            id: $setting->id,
            label: $setting->label,
            settingKey: $setting->setting_key,
            settingType: $settingKeys[$setting->setting_key] ?? $setting->setting_key,
            version: (int) $setting->version,
            scope: $setting->company?->name ?? 'Company default',
            effectiveFrom: $setting->effective_from?->format('d M Y') ?? 'Not scheduled',
            createdBy: $setting->createdBy?->name ?? 'System',
            approvalState: $setting->approvedBy?->name ? 'Approved by '.$setting->approvedBy->name : 'Approval pending',
            status: $setting->status,
            statusLabel: ucfirst($setting->status),
            statusTone: $this->statusTone($setting->status),
            governedStatutoryPack: $governed,
            statutoryValidationRequired: (bool) data_get($setting->value, 'statutory_validation_required', false),
            verified: $verified,
            verificationLabel: $governed
                ? ($verified ? 'Official-source checksum verified' : 'Independent source verification required')
                : ((bool) data_get($setting->value, 'verified', false) ? 'Legacy validation flag recorded' : 'No governed verification recorded'),
            sourceAuthority: $source['authority'],
            sourceReference: $source['reference'],
            verifiedBy: $setting->statutoryVerification?->verifiedBy?->name,
            canVerify: $this->canVerify($actor, $setting),
            canApprove: $this->canApprove($actor, $setting),
            );
        });
    }

    public function companies(User $actor): Collection
    {
        return $this->scope->apply(Company::query()->where('status', 'active'), $actor, 'id')->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function canApprove(User $actor, SystemSetting $setting): bool
    {
        if ($setting->status !== 'draft' || $setting->created_by_user_id === $actor->id) {
            return false;
        }

        if (! $actor->hasPermission('compliance.manage') && ! $actor->hasPermission('settings.approve')) {
            return false;
        }

        if ($this->isGoverned($setting)) {
            if (! $this->hasCurrentIndependentVerification($setting)) {
                return false;
            }

            if ($setting->statutoryVerification?->verified_by_user_id === $actor->id) {
                return false;
            }
        }

        return $this->scope->allowsSettingMutation($actor, $setting->company_id);
    }

    public function canVerify(User $actor, SystemSetting $setting): bool
    {
        return $setting->status === 'draft'
            && $this->isGoverned($setting)
            && $setting->created_by_user_id !== $actor->id
            && ($actor->hasPermission(LogicCenterPermissions::STATUTORY_VERIFY) || $actor->hasPermission('compliance.manage'))
            && $this->scope->allowsSettingMutation($actor, $setting->company_id);
    }

    private function settingsScope(User $actor, array $filters): Builder
    {
        $requestedCompanyId = isset($filters['company_id']) ? (int) $filters['company_id'] : null;
        $companyId = $requestedCompanyId ?? $this->scope->settingCompanyIdFor($actor);

        $query = SystemSetting::query()
            ->whereIn('setting_key', ComplianceRuleSettingIndexRequest::ALLOWED_SETTING_KEYS)
            ->when($filters['setting_key'] ?? null, fn (Builder $query, string $key) => $query->where('setting_key', $key))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        if ($requestedCompanyId !== null && ! $this->scope->allowsSettingRead($actor, $requestedCompanyId)) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyId === 0) {
            return $query->whereRaw('1 = 0');
        }

        if ($companyId === null && $requestedCompanyId === null) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($companyId): void {
            $query->whereNull('company_id');

            if ($companyId !== null) {
                $query->orWhere('company_id', $companyId);
            }
        });
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'is-success',
            'draft' => 'is-warning',
            default => 'is-muted',
        };
    }

    private function isGoverned(SystemSetting $setting): bool
    {
        return data_get($setting->value, 'governed_statutory_pack_version') === StatutoryRulePackDefinitionValidator::SCHEMA_VERSION;
    }

    /** @return array{authority:string,reference:string} */
    private function officialSource(array $value): array
    {
        $source = collect((array) ($value['source_evidence'] ?? []))
            ->first(fn (mixed $item): bool => is_array($item) && ($item['source_type'] ?? null) === 'official_government');

        if (! is_array($source)) {
            return ['authority' => 'Not recorded', 'reference' => 'No governed official source evidence'];
        }

        return [
            'authority' => trim((string) ($source['authority'] ?? '')) ?: 'Not recorded',
            'reference' => collect([$source['title'] ?? null, $source['document_reference'] ?? null])->filter()->implode(' / ') ?: 'No source reference',
        ];
    }

    private function hasCurrentIndependentVerification(SystemSetting $setting): bool
    {
        $value = (array) $setting->value;

        try {
            $this->statutoryValidator->assertValid($value);
        } catch (ValidationException) {
            return false;
        }

        $verification = $setting->statutoryVerification;

        return $verification !== null
            && $verification->verified_by_user_id !== null
            && $verification->verified_by_user_id !== $setting->created_by_user_id
            && hash_equals((string) $verification->configuration_checksum, $this->hasher->hash($value));
    }
}
