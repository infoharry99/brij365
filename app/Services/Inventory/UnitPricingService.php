<?php

namespace App\Services\Inventory;

use App\Models\ProjectUnit;
use App\Models\UnitPriceVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitPricingService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CompanyScopeService $companyScope,
        private readonly SystemSettingResolver $settings,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function relations(): array
    {
        return ['company', 'project', 'unit', 'createdBy', 'approvedBy'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createVersion(array $data, User $actor, ?Request $request = null): UnitPriceVersion
    {
        return DB::transaction(function () use ($data, $actor, $request): UnitPriceVersion {
            $unit = ProjectUnit::query()->whereKey($data['project_unit_id'])->lockForUpdate()->firstOrFail();
            $this->assertUnitScope($unit, $actor);

            $baseRate = $this->money($data['base_rate']);
            $basePrice = $this->money($baseRate * (float) $unit->saleable_area_sqft);
            $floorPremium = $this->money($data['floor_premium'] ?? 0);
            $locationPremium = $this->money($data['location_premium'] ?? 0);
            $parkingCharges = $this->money($data['parking_charges'] ?? 0);
            $otherCharges = $this->money($data['other_charges'] ?? 0);
            $taxRate = $this->money($data['tax_rate_percent'] ?? 0, 4);
            $grossBeforeTax = $this->money($basePrice + $floorPremium + $locationPremium + $parkingCharges + $otherCharges);
            $taxAmount = $this->money($grossBeforeTax * $taxRate / 100);
            $totalPrice = $this->money($grossBeforeTax + $taxAmount);

            $version = UnitPriceVersion::create([
                'company_id' => $unit->company_id,
                'project_id' => $unit->project_id,
                'project_unit_id' => $unit->id,
                'created_by_user_id' => $actor->id,
                'price_code' => $this->nextPriceCode(),
                'version_number' => $this->nextVersionNumber($unit),
                'status' => 'draft',
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'base_rate' => $baseRate,
                'base_price' => $basePrice,
                'floor_premium' => $floorPremium,
                'location_premium' => $locationPremium,
                'parking_charges' => $parkingCharges,
                'other_charges' => $otherCharges,
                'tax_rate_percent' => $taxRate,
                'gross_price_before_tax' => $grossBeforeTax,
                'tax_amount' => $taxAmount,
                'total_price' => $totalPrice,
                'charge_breakup' => $data['charge_breakup'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('draft', $actor, 'Unit price version drafted'),
                ],
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->auditLogger->record(
                $actor,
                'inventory.unit_price_version.created',
                'Created unit price version',
                $version,
                ['price_code' => $version->price_code, 'unit_code' => $unit->unit_code, 'total_price' => $version->total_price],
                $request,
            );

            return $version->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approve(UnitPriceVersion $unitPriceVersion, array $data, User $actor, ?Request $request = null): UnitPriceVersion
    {
        return DB::transaction(function () use ($unitPriceVersion, $data, $actor, $request): UnitPriceVersion {
            $version = UnitPriceVersion::query()
                ->whereKey($unitPriceVersion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($version->status !== 'draft') {
                throw ValidationException::withMessages(['unit_price_version' => 'Only draft price versions can be approved.']);
            }

            if ($version->created_by_user_id === $actor->id && ! $actor->hasPermission('*')) {
                throw ValidationException::withMessages(['unit_price_version' => 'The creator cannot approve the same price version.']);
            }

            $this->retireOverlappingActiveVersions($version);

            $history = $version->workflow_history ?? [];
            $history[] = $this->workflowEvent('active', $actor, $data['note'] ?? 'Unit price version approved');

            $version->forceFill([
                'status' => 'active',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'inventory.unit_price_version.approved',
                'Approved unit price version',
                $version,
                ['price_code' => $version->price_code, 'unit_code' => $version->unit->unit_code, 'effective_from' => $version->effective_from?->toDateString()],
                $request,
            );

            return $version->load($this->relations());
        });
    }

    public function quote(ProjectUnit $unit, User $actor, CarbonInterface|string|null $quotedOn = null, float|int|string|null $discountAmount = 0): array
    {
        $this->assertUnitScope($unit, $actor);

        $quotedOn = $quotedOn ? Carbon::parse($quotedOn) : now();
        $version = $this->activeVersionFor($unit, $quotedOn);
        $discount = $this->money($discountAmount ?? 0);

        if ($version) {
            $grossBeforeTax = (float) $version->gross_price_before_tax;
            $taxRate = (float) $version->tax_rate_percent;
            $taxableAfterDiscount = $this->money(max($grossBeforeTax - $discount, 0));
            $taxAmount = $this->money($taxableAfterDiscount * $taxRate / 100);
            $totalPayable = $this->money($taxableAfterDiscount + $taxAmount);

            $quote = [
                'source' => 'unit_price_version',
                'unit_price_version_id' => $version->id,
                'price_code' => $version->price_code,
                'version_number' => $version->version_number,
                'effective_from' => $version->effective_from?->toDateString(),
                'effective_to' => $version->effective_to?->toDateString(),
                'base_rate' => (float) $version->base_rate,
                'base_price' => (float) $version->base_price,
                'floor_premium' => (float) $version->floor_premium,
                'location_premium' => (float) $version->location_premium,
                'parking_charges' => (float) $version->parking_charges,
                'other_charges' => (float) $version->other_charges,
                'gross_price_before_tax' => $grossBeforeTax,
                'discount_amount' => $discount,
                'taxable_amount' => $taxableAfterDiscount,
                'tax_rate_percent' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_payable' => $totalPayable,
                'charge_breakup' => $version->charge_breakup ?? [],
            ];
        } else {
            $grossBeforeTax = $this->money((float) $unit->total_price - (float) $unit->tax_amount);
            $taxableAfterDiscount = $this->money(max((float) $unit->total_price - $discount, 0));
            $quote = [
                'source' => 'project_unit_snapshot',
                'unit_price_version_id' => null,
                'price_code' => null,
                'version_number' => null,
                'effective_from' => null,
                'effective_to' => null,
                'base_rate' => (float) $unit->base_rate,
                'base_price' => (float) $unit->base_price,
                'floor_premium' => (float) $unit->floor_rise,
                'location_premium' => 0.0,
                'parking_charges' => (float) $unit->parking_charges,
                'other_charges' => (float) $unit->other_charges,
                'gross_price_before_tax' => $grossBeforeTax,
                'discount_amount' => $discount,
                'taxable_amount' => $taxableAfterDiscount,
                'tax_rate_percent' => null,
                'tax_amount' => (float) $unit->tax_amount,
                'total_payable' => $taxableAfterDiscount,
                'charge_breakup' => [],
            ];
        }

        $quote['quoted_on'] = $quotedOn->toDateString();
        $quote['unit'] = [
            'id' => $unit->id,
            'unit_code' => $unit->unit_code,
            'unit_type' => $unit->unit_type,
            'saleable_area_sqft' => (float) $unit->saleable_area_sqft,
            'status' => $unit->status,
        ];
        $quote['discount_policy'] = $this->discountPolicy($unit->company_id);
        $quote['requires_discount_approval'] = $this->requiresDiscountApproval($unit->company_id, $quote);

        return $quote;
    }

    public function activeVersionFor(ProjectUnit $unit, CarbonInterface $effectiveOn): ?UnitPriceVersion
    {
        $effectiveDate = Carbon::parse($effectiveOn)->toDateString();

        return UnitPriceVersion::query()
            ->where('project_unit_id', $unit->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('version_number')
            ->first();
    }

    /**
     * @param array<string, mixed> $quote
     */
    public function assertDiscountAllowed(ProjectUnit $unit, User $actor, array $quote): void
    {
        if (! ($quote['requires_discount_approval'] ?? false)) {
            return;
        }

        if ($actor->hasPermission('*') || $actor->hasPermission('finance.approve')) {
            return;
        }

        throw ValidationException::withMessages([
            'discount_amount' => 'The requested discount exceeds the configured direct approval limit.',
        ]);
    }

    private function retireOverlappingActiveVersions(UnitPriceVersion $replacement): void
    {
        $replacementStart = $replacement->effective_from->copy()->startOfDay();

        $activeVersions = UnitPriceVersion::query()
            ->where('project_unit_id', $replacement->project_unit_id)
            ->where('status', 'active')
            ->whereKeyNot($replacement->id)
            ->lockForUpdate()
            ->get();

        foreach ($activeVersions as $activeVersion) {
            $activeEnd = $activeVersion->effective_to?->copy()->startOfDay();

            if ($activeEnd !== null && $activeEnd->lt($replacementStart)) {
                continue;
            }

            $newEnd = $replacementStart->copy()->subDay();
            $activeVersion->forceFill([
                'status' => $newEnd->lt(now()->startOfDay()) ? 'archived' : 'active',
                'effective_to' => $newEnd->toDateString(),
            ])->save();
        }
    }

    private function assertUnitScope(ProjectUnit $unit, User $actor): void
    {
        if (! $this->companyScope->allows($actor, $unit->company_id)) {
            throw ValidationException::withMessages(['project_unit_id' => 'The selected unit is not available for your company.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function discountPolicy(int $companyId): array
    {
        return $this->settings->value($companyId, 'sales.pricing.rules', [
            'max_direct_discount_percent' => 5,
            'approval_chain' => ['sales_preparer', 'finance_approver'],
            'segregation_of_duties' => true,
            'effective_pricing_required' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $quote
     */
    private function requiresDiscountApproval(int $companyId, array $quote): bool
    {
        $policy = $this->discountPolicy($companyId);
        $gross = (float) $quote['gross_price_before_tax'];
        $discount = (float) $quote['discount_amount'];

        if ($discount <= 0 || $gross <= 0) {
            return false;
        }

        $percent = ($discount / $gross) * 100;

        return $percent > (float) ($policy['max_direct_discount_percent'] ?? 5);
    }

    /**
     * @return array<string, string|int>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextVersionNumber(ProjectUnit $unit): int
    {
        return (int) UnitPriceVersion::query()
            ->where('project_unit_id', $unit->id)
            ->max('version_number') + 1;
    }

    private function nextPriceCode(): string
    {
        return sprintf('UPV-%05d', UnitPriceVersion::query()->withTrashed()->count() + 10001);
    }

    private function money(float|int|string|null $amount, int $precision = 2): float
    {
        return round((float) ($amount ?? 0), $precision);
    }
}
