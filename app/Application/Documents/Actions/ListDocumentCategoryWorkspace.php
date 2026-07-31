<?php

namespace App\Application\Documents\Actions;

use App\Application\Documents\Data\DocumentCategoryWorkspaceData;
use App\Domain\Documents\Services\DocumentCategoryRegister;
use App\Models\User;

final class ListDocumentCategoryWorkspace
{
    public function __construct(private readonly DocumentCategoryRegister $register) {}

    public function execute(User $actor, array $filters): DocumentCategoryWorkspaceData
    {
        return new DocumentCategoryWorkspaceData(
            categories: $this->register->categories($actor, $filters),
            filters: $filters,
            ownerTypes: [
                'global' => 'Company-wide',
                'project' => 'Project',
                'booking' => 'Booking',
                'customer' => 'Customer',
                'employee' => 'Employee',
            ],
        );
    }
}
