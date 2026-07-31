<?php

namespace App\Domain\Procurement\Services;

use App\Application\Procurement\Data\VendorPerformanceEvidenceData;
use App\Application\Scoring\Actions\CalculateAndStoreScore;
use App\Models\ScoreSnapshot;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class VendorPerformanceScoringService
{
    public function __construct(private CalculateAndStoreScore $calculate) {}

    public function updateAndCalculate(Vendor $vendor, VendorPerformanceEvidenceData $evidence): ScoreSnapshot
    {
        return DB::transaction(function () use ($vendor, $evidence): ScoreSnapshot {
            $locked = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['vendor' => 'Performance evidence can be updated only for an active vendor.']);
            }

            $inputs = $evidence->toArray();
            $locked->forceFill(['scoring_inputs' => $inputs])->save();

            return $this->calculate->handle(
                (int) $locked->company_id,
                'vendor_performance',
                Vendor::class,
                (int) $locked->id,
                $inputs,
                ['vendor_code' => $locked->vendor_code],
            );
        });
    }
}
