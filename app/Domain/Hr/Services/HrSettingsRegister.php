<?php

namespace App\Domain\Hr\Services;

use App\Application\Hr\Data\HrSettingRowData;
use App\Models\SystemSetting;
use App\Models\User;
use App\Support\PaginationPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class HrSettingsRegister
{
    public function __construct(private readonly PaginationPolicy $pagination) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function settings(User $actor, array $filters): LengthAwarePaginator
    {
        return $this->filteredQuery($actor, $filters)
            ->with(['company', 'createdBy', 'approvedBy'])
            ->orderBy('setting_group')
            ->orderBy('setting_key')
            ->orderByDesc('version')
            ->paginate($this->pagination->workspacePerPage())
            ->withQueryString()
            ->through(fn (SystemSetting $setting): HrSettingRowData => $this->toRow($setting, $actor));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function summary(User $actor, array $filters): array
    {
        $query = $this->filteredQuery($actor, $filters, applyStatus: false);

        return [
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'archived' => (clone $query)->where('status', 'archived')->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(User $actor, array $filters, bool $applyStatus = true): Builder
    {
        $query = SystemSetting::query()
            ->where(function (Builder $allowed): void {
                $allowed->whereIn('setting_group', ['hr', 'payroll'])
                    ->orWhere('setting_key', 'workflow.approval_chains');
            });

        // The application is operating in one-company mode. Even global roles
        // may inspect only shared defaults and the authenticated user's company.
        $query->where(function (Builder $scope) use ($actor): void {
            $scope->whereNull('company_id');

            if ($actor->company_id !== null) {
                $scope->orWhere('company_id', $actor->company_id);
            }
        });

        $tab = $filters['tab'] ?? 'overview';
        if ($tab === 'hr') {
            $query->where('setting_group', 'hr');
        } elseif ($tab === 'payroll') {
            $query->where('setting_group', 'payroll');
        } elseif ($tab === 'workflow') {
            $query->where('setting_key', 'workflow.approval_chains');
        }

        $query->when($filters['search'] ?? null, function (Builder $searchQuery, string $search): void {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $searchQuery->where(function (Builder $matching) use ($term): void {
                $matching->where('setting_key', 'like', $term)
                    ->orWhere('label', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        });

        if ($applyStatus) {
            $query->when($filters['status'] ?? null, fn (Builder $statusQuery, string $status) => $statusQuery->where('status', $status));
        }

        return $query;
    }

    private function toRow(SystemSetting $setting, User $actor): HrSettingRowData
    {
        $statusTone = match ($setting->status) {
            'active' => 'is-success',
            'draft' => 'is-warning',
            default => 'is-muted',
        };

        $value = $setting->value;
        $configuredCount = is_array($value) ? count($value) : 0;
        $valueSummary = $configuredCount > 0
            ? $configuredCount.' configured '.Str::plural('field', $configuredCount)
            : 'No configured fields';

        $effectiveLabel = $setting->effective_from?->format('d M Y') ?? 'Not scheduled';
        if ($setting->effective_to !== null) {
            $effectiveLabel .= ' to '.$setting->effective_to->format('d M Y');
        }

        return new HrSettingRowData(
            id: $setting->id,
            settingKey: $setting->setting_key,
            settingGroup: $setting->setting_group,
            label: $setting->label,
            description: $setting->description ?: 'No description recorded.',
            scopeLabel: $setting->company_id === null ? 'Shared default' : 'Company policy',
            versionLabel: 'v'.$setting->version,
            typeLabel: Str::headline($setting->value_type),
            valueSummary: $valueSummary,
            status: $setting->status,
            statusLabel: Str::headline($setting->status),
            statusTone: $statusTone,
            effectiveLabel: $effectiveLabel,
            makerLabel: $setting->createdBy?->name ?? 'System',
            checkerLabel: $setting->approvedBy?->name ?? 'Awaiting approval',
            canApprove: $actor->can('approve', $setting),
        );
    }
}
