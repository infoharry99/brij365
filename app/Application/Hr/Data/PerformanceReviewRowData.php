<?php

namespace App\Application\Hr\Data;

final readonly class PerformanceReviewRowData
{
    public function __construct(
        public int $id,
        public int $lockVersion,
        public string $number,
        public string $employeeCode,
        public string $employeeName,
        public string $department,
        public string $managerName,
        public string $cycleName,
        public string $period,
        public int $ratingScaleMin,
        public int $ratingScaleMax,
        public ?string $selfScore,
        public ?string $managerScore,
        public ?string $finalScore,
        public ?string $finalRating,
        public ?string $formulaScore,
        public ?string $formulaRating,
        public ?int $scoringRuleVersion,
        public ?string $scoringRuleChecksum,
        public ?string $scoringCalculatedAt,
        public bool $scoreIsOverride,
        public array $calculationTrace,
        public ?int $overrideRequestId,
        public ?string $overrideStatus,
        public ?string $overrideRequestedScore,
        public ?string $overrideRequester,
        public string $status,
        public string $statusLabel,
        public bool $pipRequired,
        public bool $canSubmitSelf,
        public bool $canSubmitManager,
        public bool $canCalibrate,
        public bool $canRequestOverride,
        public bool $canDecideOverride,
        public bool $canClose,
    ) {}
}
