<?php

namespace App\Application\Sales\Actions;

use App\Application\Sales\Data\SalesCommandData;
use App\Models\ProjectUnit;
use App\Services\Inventory\UnitPricingService;
use Illuminate\Support\Carbon;

final class QuoteBooking
{
    public function __construct(private readonly UnitPricingService $pricing) {}

    /** @return array<string, mixed> */
    public function execute(SalesCommandData $command): array
    {
        $data = $command->attributes;
        $unit = ProjectUnit::query()->whereKey($data['project_unit_id'])->firstOrFail();

        return $this->pricing->quote(
            $unit,
            $command->actor,
            isset($data['quoted_on']) ? Carbon::parse($data['quoted_on']) : null,
            $data['discount_amount'] ?? 0,
        );
    }
}
