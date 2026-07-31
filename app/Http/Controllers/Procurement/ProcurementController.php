<?php

namespace App\Http\Controllers\Procurement;

use App\Application\Procurement\Actions\ApprovePurchaseOrder;
use App\Application\Procurement\Actions\ApprovePurchaseRequisition;
use App\Application\Procurement\Actions\ChangeVendorStatus;
use App\Application\Procurement\Actions\CompareProcurementQuotes;
use App\Application\Procurement\Actions\CreatePurchaseOrder;
use App\Application\Procurement\Actions\CreateVendor;
use App\Application\Procurement\Actions\IssueStock;
use App\Application\Procurement\Actions\ListGoodsReceipts;
use App\Application\Procurement\Actions\ListProcurementWorkspace;
use App\Application\Procurement\Actions\ListPurchaseOrders;
use App\Application\Procurement\Actions\ListPurchaseRequisitions;
use App\Application\Procurement\Actions\ListStockItems;
use App\Application\Procurement\Actions\ListVendors;
use App\Application\Procurement\Actions\ReceiveGoods;
use App\Application\Procurement\Actions\ReturnStock;
use App\Application\Procurement\Actions\SubmitPurchaseRequisition;
use App\Application\Procurement\Actions\TransferStock;
use App\Application\Procurement\Actions\UpdateVendor;
use App\Application\Procurement\Actions\ViewProcurementDashboard;
use App\Application\Procurement\Actions\ViewVendorPerformance;
use App\Application\Procurement\Data\ProcurementCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\ApprovePurchaseOrderRequest;
use App\Http\Requests\Procurement\ApprovePurchaseRequisitionRequest;
use App\Http\Requests\Procurement\GoodsReceiptIndexRequest;
use App\Http\Requests\Procurement\ProcurementDashboardRequest;
use App\Http\Requests\Procurement\ProcurementQuoteComparisonRequest;
use App\Http\Requests\Procurement\PurchaseOrderIndexRequest;
use App\Http\Requests\Procurement\PurchaseRequisitionIndexRequest;
use App\Http\Requests\Procurement\StockItemIndexRequest;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseRequisitionRequest;
use App\Http\Requests\Procurement\StoreStockIssueRequest;
use App\Http\Requests\Procurement\StoreStockReturnRequest;
use App\Http\Requests\Procurement\StoreStockTransferRequest;
use App\Http\Requests\Procurement\StoreVendorRequest;
use App\Http\Requests\Procurement\UpdateVendorRequest;
use App\Http\Requests\Procurement\UpdateVendorStatusRequest;
use App\Http\Requests\Procurement\VendorIndexRequest;
use App\Http\Requests\Procurement\VendorPerformanceRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Http\Resources\PurchaseOrderResource;
use App\Http\Resources\PurchaseRequisitionResource;
use App\Http\Resources\StockItemResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\VendorResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function dashboard(ProcurementDashboardRequest $request, ViewProcurementDashboard $dashboardAction, ListProcurementWorkspace $workspace): JsonResponse|View
    {
        $dashboard = $dashboardAction->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('procurement.workspace.index', $workspace->execute($request->user(), $request->validated(), 'dashboard', dashboard: $dashboard)->toView());
        }

        return response()->json([
            'data' => $dashboard,
        ]);
    }

    public function vendors(VendorIndexRequest $request, ListVendors $list, ListProcurementWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $vendors = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('procurement.workspace.index', $workspace->execute($request->user(), $validated, 'vendors', vendors: $vendors)->toView());
        }

        return VendorResource::collection($vendors);
    }

    public function vendorPerformance(Vendor $vendor, VendorPerformanceRequest $request, ViewVendorPerformance $view): JsonResponse
    {
        return response()->json([
            'data' => $view->execute($vendor, $request->user()),
        ]);
    }

    public function storeVendor(StoreVendorRequest $request, CreateVendor $create): VendorResource|RedirectResponse
    {
        $vendor = $create->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('procurement.vendors.index', ['vendor_type' => $vendor->vendor_type, 'status' => $vendor->status])
                ->with('status', "Vendor {$vendor->vendor_code} created.");
        }

        return (new VendorResource($vendor))->additional(['message' => 'Vendor master created.']);
    }

    public function updateVendor(Vendor $vendor, UpdateVendorRequest $request, UpdateVendor $update): VendorResource
    {
        $vendor = $update->execute($vendor, new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new VendorResource($vendor))->additional(['message' => 'Vendor master updated.']);
    }

    public function updateVendorStatus(Vendor $vendor, UpdateVendorStatusRequest $request, ChangeVendorStatus $change): VendorResource|RedirectResponse
    {
        $vendor = $change->execute($vendor, new ProcurementCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('procurement.vendors.index', ['status' => $vendor->status])
                ->with('status', "Vendor {$vendor->vendor_code} status updated to {$vendor->status}.");
        }

        return (new VendorResource($vendor))->additional(['message' => 'Vendor status updated.']);
    }

    public function requisitions(PurchaseRequisitionIndexRequest $request, ListPurchaseRequisitions $list, ListProcurementWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $requisitions = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('procurement.workspace.index', $workspace->execute($request->user(), $validated, 'requisitions', requisitions: $requisitions)->toView());
        }

        return PurchaseRequisitionResource::collection($requisitions);
    }

    public function storeRequisition(StorePurchaseRequisitionRequest $request, SubmitPurchaseRequisition $submit): PurchaseRequisitionResource|RedirectResponse
    {
        $requisition = $submit->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('procurement.requisitions.index', ['project_id' => $requisition->project_id, 'status' => 'submitted'])
                ->with('status', "Purchase requisition {$requisition->requisition_number} submitted.");
        }

        return (new PurchaseRequisitionResource($requisition))->additional(['message' => 'Purchase requisition submitted.']);
    }

    public function approveRequisition(PurchaseRequisition $purchaseRequisition, ApprovePurchaseRequisitionRequest $request, ApprovePurchaseRequisition $approve): PurchaseRequisitionResource|RedirectResponse
    {
        $requisition = $approve->execute($purchaseRequisition, new ProcurementCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('procurement.requisitions.index', ['project_id' => $requisition->project_id, 'status' => 'approved'])
                ->with('status', "Purchase requisition {$requisition->requisition_number} approved.");
        }

        return (new PurchaseRequisitionResource($requisition))->additional(['message' => 'Purchase requisition approved.']);
    }

    public function quoteComparison(PurchaseRequisition $purchaseRequisition, ProcurementQuoteComparisonRequest $request, CompareProcurementQuotes $compare): JsonResponse
    {
        return response()->json([
            'data' => $compare->execute($purchaseRequisition, $request->user()),
        ]);
    }

    public function purchaseOrders(PurchaseOrderIndexRequest $request, ListPurchaseOrders $list): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $purchaseOrders = $list->execute($request->user(), $validated);

        return PurchaseOrderResource::collection($purchaseOrders);
    }

    public function storePurchaseOrder(StorePurchaseOrderRequest $request, CreatePurchaseOrder $create): PurchaseOrderResource
    {
        $purchaseOrder = $create->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new PurchaseOrderResource($purchaseOrder))->additional(['message' => 'Purchase order draft created.']);
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, ApprovePurchaseOrderRequest $request, ApprovePurchaseOrder $approve): PurchaseOrderResource
    {
        $order = $approve->execute($purchaseOrder, new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new PurchaseOrderResource($order))->additional(['message' => 'Purchase order approved.']);
    }

    public function goodsReceipts(GoodsReceiptIndexRequest $request, ListGoodsReceipts $list): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $receipts = $list->execute($request->user(), $validated);

        return GoodsReceiptResource::collection($receipts);
    }

    public function storeGoodsReceipt(StoreGoodsReceiptRequest $request, ReceiveGoods $receive): GoodsReceiptResource
    {
        $receipt = $receive->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new GoodsReceiptResource($receipt))->additional(['message' => 'Goods receipt created.']);
    }

    public function stockItems(StockItemIndexRequest $request, ListStockItems $list, ListProcurementWorkspace $workspace): AnonymousResourceCollection|View
    {
        $validated = $request->validated();

        $stockItems = $list->execute($request->user(), $validated);

        if (! $request->wantsJson()) {
            return view('procurement.workspace.index', $workspace->execute($request->user(), $validated, 'stock', stockItems: $stockItems)->toView());
        }

        return StockItemResource::collection($stockItems);
    }

    public function storeStockIssue(StoreStockIssueRequest $request, IssueStock $issue): StockMovementResource
    {
        $movement = $issue->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new StockMovementResource($movement))->additional(['message' => 'Stock movement recorded.']);
    }

    public function storeStockReturn(StoreStockReturnRequest $request, ReturnStock $return): StockMovementResource
    {
        $movement = $return->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        return (new StockMovementResource($movement))->additional(['message' => 'Stock return recorded.']);
    }

    public function storeStockTransfer(StoreStockTransferRequest $request, TransferStock $transfer): AnonymousResourceCollection
    {
        $movements = $transfer->execute(new ProcurementCommandData($request->validated(), $request->user(), $request));

        return StockMovementResource::collection($movements)->additional(['message' => 'Stock transfer recorded.']);
    }
}
