<?php

namespace App\Application\Documents\Actions;

use App\Application\Documents\Data\ManagedDocumentWorkspaceData;
use App\Domain\Documents\Services\ManagedDocumentRegister;
use App\Models\ManagedDocument;
use App\Models\User;

final class ListManagedDocumentWorkspace
{
    public function __construct(private readonly ManagedDocumentRegister $register) {}

    public function execute(User $actor, array $filters): ManagedDocumentWorkspaceData
    {
        return new ManagedDocumentWorkspaceData(
            documents: $this->register->documents($actor, $filters),
            filters: $filters,
            categories: $this->register->categories($actor),
            projects: $this->register->projects($actor),
            bookings: $this->register->bookings($actor),
            customers: $this->register->customers($actor),
            employees: $this->register->employees($actor),
            ownerTypes: [
                'project' => 'Project',
                'booking' => 'Booking',
                'customer' => 'Customer',
                'employee' => 'Employee',
            ],
            statuses: [
                'submitted' => 'Submitted',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'archived' => 'Archived',
            ],
            abilities: [
                'canCreateDocument' => $actor->can('create', ManagedDocument::class),
            ],
        );
    }
}
