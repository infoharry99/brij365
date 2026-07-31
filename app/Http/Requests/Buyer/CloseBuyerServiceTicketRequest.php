<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\AfterSales\CloseServiceTicketRequest;
use App\Models\ServiceTicket;

class CloseBuyerServiceTicketRequest extends CloseServiceTicketRequest
{
    public function authorize(): bool
    {
        $serviceTicket = $this->route('serviceTicket');
        $user = $this->user();

        return $user?->role?->slug === 'buyer'
            && $user->role?->scope_level === 'self'
            && $user->hasPermission('buyer.view') === true
            && $serviceTicket instanceof ServiceTicket
            && $user->can('close', $serviceTicket) === true;
    }
}
