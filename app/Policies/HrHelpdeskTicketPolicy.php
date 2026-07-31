<?php

namespace App\Policies;

use App\Models\HrHelpdeskTicket;
use App\Models\User;
use App\Services\Security\CompanyScopeService;

class HrHelpdeskTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('helpdesk.view') || $user->hasPermission('helpdesk.manage')
            || $user->hasPermission('hr.manage') || $user->hasPermission('employee.self_service');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employee.self_service') || $user->hasPermission('helpdesk.manage') || $user->hasPermission('hr.manage');
    }

    public function manage(User $user, HrHelpdeskTicket $ticket): bool
    {
        return ($user->hasPermission('helpdesk.manage') || $user->hasPermission('hr.manage'))
            && app(CompanyScopeService::class)->allows($user, $ticket->company_id);
    }

    public function close(User $user, HrHelpdeskTicket $ticket): bool
    {
        if ($ticket->status !== 'resolved') {
            return false;
        }

        return $this->manage($user, $ticket)
            || ($user->hasPermission('employee.self_service') && $ticket->employee()->where('user_id', $user->id)->exists());
    }
}
