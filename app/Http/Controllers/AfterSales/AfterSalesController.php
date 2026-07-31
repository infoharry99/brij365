<?php

namespace App\Http\Controllers\AfterSales;

use App\Application\AfterSales\Actions\CloseServiceTicketAction;
use App\Application\AfterSales\Actions\AssignServiceTicket;
use App\Application\AfterSales\Actions\CompleteMaintenanceWorkOrder;
use App\Application\AfterSales\Actions\CreateMaintenanceWorkOrder;
use App\Application\AfterSales\Actions\CreateServiceTicket;
use App\Application\AfterSales\Actions\ListMaintenanceWorkOrderWorkspace;
use App\Application\AfterSales\Actions\ListServiceTicketWorkspace;
use App\Application\AfterSales\Actions\ResolveServiceTicket;
use App\Application\AfterSales\Data\AfterSalesCommandData;
use App\Application\AfterSales\Data\CloseServiceTicketData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AfterSales\AssignServiceTicketRequest;
use App\Http\Requests\AfterSales\CloseServiceTicketRequest;
use App\Http\Requests\AfterSales\CompleteMaintenanceWorkOrderRequest;
use App\Http\Requests\AfterSales\MaintenanceWorkOrderIndexRequest;
use App\Http\Requests\AfterSales\ResolveServiceTicketRequest;
use App\Http\Requests\AfterSales\ServiceTicketIndexRequest;
use App\Http\Requests\AfterSales\StoreMaintenanceWorkOrderRequest;
use App\Http\Requests\AfterSales\StoreServiceTicketRequest;
use App\Http\Resources\MaintenanceWorkOrderResource;
use App\Http\Resources\ServiceTicketResource;
use App\Models\MaintenanceWorkOrder;
use App\Models\ServiceTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class AfterSalesController extends Controller
{
    public function tickets(ServiceTicketIndexRequest $request, ListServiceTicketWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return ServiceTicketResource::collection($workspace->tickets);
        }

        return view('after-sales.tickets.index', $workspace->toView());
    }

    public function storeTicket(StoreServiceTicketRequest $request, CreateServiceTicket $create): ServiceTicketResource|RedirectResponse
    {
        $ticket = $create->execute(new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.tickets.index')
                ->with('status', "Service ticket {$ticket->ticket_number} created.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'After-sales service ticket created.']);
    }

    public function assignTicket(
        ServiceTicket $serviceTicket,
        AssignServiceTicketRequest $request,
        AssignServiceTicket $assign,
    ): ServiceTicketResource|RedirectResponse {
        $ticket = $assign->execute($serviceTicket, new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.tickets.index')
                ->with('status', "Service ticket {$ticket->ticket_number} assigned.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'Service ticket assigned.']);
    }

    public function resolveTicket(
        ServiceTicket $serviceTicket,
        ResolveServiceTicketRequest $request,
        ResolveServiceTicket $resolve,
    ): ServiceTicketResource|RedirectResponse {
        $ticket = $resolve->execute($serviceTicket, new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.tickets.index')
                ->with('status', "Service ticket {$ticket->ticket_number} resolved.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'Service ticket resolved.']);
    }

    public function closeTicket(
        ServiceTicket $serviceTicket,
        CloseServiceTicketRequest $request,
        CloseServiceTicketAction $action,
    ): ServiceTicketResource|RedirectResponse {
        $ticket = $action->execute(
            $serviceTicket,
            CloseServiceTicketData::from($request->validated()),
            $request->user(),
            $request,
        );

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.tickets.index')
                ->with('status', "Service ticket {$ticket->ticket_number} closed.");
        }

        return (new ServiceTicketResource($ticket))->additional(['message' => 'Service ticket closed.']);
    }

    public function workOrders(MaintenanceWorkOrderIndexRequest $request, ListMaintenanceWorkOrderWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return MaintenanceWorkOrderResource::collection($workspace->workOrders);
        }

        return view('after-sales.work-orders.index', $workspace->toView());
    }

    public function storeWorkOrder(StoreMaintenanceWorkOrderRequest $request, CreateMaintenanceWorkOrder $create): MaintenanceWorkOrderResource|RedirectResponse
    {
        $workOrder = $create->execute(new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.work-orders.index')
                ->with('status', "Maintenance work order {$workOrder->work_order_number} created.");
        }

        return (new MaintenanceWorkOrderResource($workOrder))->additional(['message' => 'Maintenance work order created.']);
    }

    public function completeWorkOrder(
        MaintenanceWorkOrder $maintenanceWorkOrder,
        CompleteMaintenanceWorkOrderRequest $request,
        CompleteMaintenanceWorkOrder $complete,
    ): MaintenanceWorkOrderResource|RedirectResponse {
        $workOrder = $complete->execute($maintenanceWorkOrder, new AfterSalesCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('after-sales.work-orders.index')
                ->with('status', "Maintenance work order {$workOrder->work_order_number} completed.");
        }

        return (new MaintenanceWorkOrderResource($workOrder))->additional(['message' => 'Maintenance work order completed.']);
    }
}
