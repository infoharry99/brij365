<?php

namespace App\Http\Requests\Buyer;

use App\Http\Requests\AfterSales\StoreServiceTicketRequest;
use App\Models\ServiceTicket;

class StoreBuyerServiceTicketRequest extends StoreServiceTicketRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role?->slug === 'buyer'
            && $user->role?->scope_level === 'self'
            && $user->hasPermission('buyer.view') === true
            && $user->can('create', ServiceTicket::class) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'source' => 'portal',
        ]);
    }
}
