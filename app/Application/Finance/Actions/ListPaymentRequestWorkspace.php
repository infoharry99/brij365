<?php
namespace App\Application\Finance\Actions;
use App\Application\Finance\Data\PaymentRequestWorkspaceData;
use App\Domain\Finance\Services\FinanceWorkspaceRegister;
use App\Models\Customer;
use App\Models\PaymentRequest;
use App\Models\User;
final class ListPaymentRequestWorkspace
{
    public function __construct(private readonly FinanceWorkspaceRegister $register) {}
    public function execute(User $actor, array $filters): PaymentRequestWorkspaceData
    {
        $bookings=$this->register->paymentRequestBookings($actor);
        return new PaymentRequestWorkspaceData(
            paymentRequests:$this->register->paymentRequests($actor,$filters), filters:$filters,
            projects:$this->register->projects($actor), bookings:$bookings,
            customers:Customer::query()->whereIn('id',$bookings->pluck('customer_id')->filter()->unique()->values())->orderBy('name')->get(['id','code','name']),
            statuses:['requested'=>'Requested','paid'=>'Paid','cancelled'=>'Cancelled','expired'=>'Expired','failed'=>'Failed'],
            abilities:['canCreatePaymentRequest'=>$actor->can('create',PaymentRequest::class)],
        );
    }
}
