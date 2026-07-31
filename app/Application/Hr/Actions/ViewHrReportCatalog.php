<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrReportCatalogData;
use App\Domain\Hr\Services\EmployeeFieldVisibility;
use App\Domain\Hr\Services\HrReportCatalog;
use App\Models\User;

final class ViewHrReportCatalog
{
    public function __construct(
        private readonly HrReportCatalog $catalog,
        private readonly EmployeeFieldVisibility $fieldVisibility,
    ) {}

    public function execute(User $actor): HrReportCatalogData
    {
        return new HrReportCatalogData(
            reports: $this->catalog->for($actor),
            compensationVisible: $this->fieldVisibility->canViewCompensation($actor),
        );
    }
}
