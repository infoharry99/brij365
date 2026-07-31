<?php

namespace App\Services\Procurement;

use App\Models\GoodsReceipt;
use App\Models\FinancialVoucher;
use App\Models\FinancialVoucherLine;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly CompanyScopeService $companyScope,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function dashboard(array $filters, User $actor): array
    {
        $vendorQuery = Vendor::query();
        $this->companyScope->apply($vendorQuery, $actor);

        $requisitionQuery = PurchaseRequisition::query();
        $this->companyScope->apply($requisitionQuery, $actor);
        $this->applyProcurementDashboardFilters($requisitionQuery, $filters, 'required_by');

        $purchaseOrderQuery = PurchaseOrder::query()->with(['project', 'vendor', 'goodsReceipts']);
        $this->companyScope->apply($purchaseOrderQuery, $actor);
        $this->applyProcurementDashboardFilters($purchaseOrderQuery, $filters, 'po_date', true);

        $goodsReceiptQuery = GoodsReceipt::query();
        $this->companyScope->apply($goodsReceiptQuery, $actor);
        $this->applyProcurementDashboardFilters($goodsReceiptQuery, $filters, 'received_on');
        $goodsReceiptQuery->when($filters['vendor_id'] ?? null, function ($query, int $vendorId): void {
            $query->whereHas('purchaseOrder', fn ($purchaseOrderQuery) => $purchaseOrderQuery->where('vendor_id', $vendorId));
        });

        $stockItemQuery = StockItem::query()->with('project');
        $this->companyScope->apply($stockItemQuery, $actor);
        $this->applyStockDashboardFilters($stockItemQuery, $filters);

        $purchaseOrders = $purchaseOrderQuery->get();
        $stockItems = $stockItemQuery->get();
        $pendingDeliveries = $this->pendingDeliveries($purchaseOrders);

        return [
            'filters' => [
                'project_id' => isset($filters['project_id']) ? (int) $filters['project_id'] : null,
                'vendor_id' => isset($filters['vendor_id']) ? (int) $filters['vendor_id'] : null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'summary' => [
                'active_vendors' => (clone $vendorQuery)->where('status', 'active')->count(),
                'requisitions' => [
                    'total' => (clone $requisitionQuery)->count(),
                    'submitted' => (clone $requisitionQuery)->where('status', 'submitted')->count(),
                    'approved' => (clone $requisitionQuery)->where('status', 'approved')->count(),
                    'estimated_total' => round((float) (clone $requisitionQuery)->sum('estimated_total'), 2),
                ],
                'purchase_orders' => [
                    'total' => $purchaseOrders->count(),
                    'draft' => $purchaseOrders->where('status', 'draft')->count(),
                    'approved' => $purchaseOrders->where('status', 'approved')->count(),
                    'partially_received' => $purchaseOrders->where('status', 'partially_received')->count(),
                    'received' => $purchaseOrders->where('status', 'received')->count(),
                    'total_amount' => round((float) $purchaseOrders->sum(fn (PurchaseOrder $order): float => (float) $order->total_amount), 2),
                ],
                'goods_receipts' => [
                    'total' => (clone $goodsReceiptQuery)->count(),
                    'accepted_total' => round((float) (clone $goodsReceiptQuery)->sum('accepted_total'), 2),
                ],
                'stock' => [
                    'items' => $stockItems->count(),
                    'stock_value' => round((float) $stockItems->sum(fn (StockItem $item): float => (float) $item->stock_value), 2),
                    'low_stock_items' => $stockItems->filter(fn (StockItem $item): bool => $item->isBelowMinimum())->count(),
                ],
                'pending_delivery' => [
                    'purchase_orders' => $pendingDeliveries->pluck('po_number')->unique()->count(),
                    'lines' => $pendingDeliveries->count(),
                    'quantity' => round((float) $pendingDeliveries->sum('pending_quantity'), 3),
                    'amount' => round((float) $pendingDeliveries->sum('pending_amount'), 2),
                    'overdue_lines' => $pendingDeliveries->where('is_overdue', true)->count(),
                ],
            ],
            'pending_deliveries' => $pendingDeliveries->values()->all(),
            'low_stock_items' => $stockItems
                ->filter(fn (StockItem $item): bool => $item->isBelowMinimum())
                ->sortBy('item_code')
                ->values()
                ->map(fn (StockItem $item): array => [
                    'id' => $item->id,
                    'project_id' => $item->project_id,
                    'project_code' => $item->project?->code,
                    'store_type' => $item->store_type,
                    'item_code' => $item->item_code,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'on_hand_quantity' => (float) $item->on_hand_quantity,
                    'minimum_stock_quantity' => (float) $item->minimum_stock_quantity,
                    'stock_value' => (float) $item->stock_value,
                    'last_movement_at' => $item->last_movement_at?->toISOString(),
                ])
                ->all(),
            'status_breakdown' => [
                'requisitions' => (clone $requisitionQuery)
                    ->selectRaw('status, count(*) as total')
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->map(fn (mixed $count): int => (int) $count)
                    ->all(),
                'purchase_orders' => $purchaseOrders
                    ->groupBy('status')
                    ->map(fn (Collection $orders): int => $orders->count())
                    ->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createVendor(array $data, User $actor, ?Request $request = null): Vendor
    {
        return DB::transaction(function () use ($data, $actor, $request): Vendor {
            $companyId = $this->companyIdForVendorWrite($data, $actor);

            $vendor = Vendor::create($this->vendorPayload($data, [
                'company_id' => $companyId,
                'vendor_code' => strtoupper((string) $data['vendor_code']),
                'status' => $data['status'] ?? 'active',
            ]));

            $this->auditLogger->record(
                $actor,
                'procurement.vendor.created',
                'Created vendor master record',
                $vendor,
                [
                    'vendor_code' => $vendor->vendor_code,
                    'vendor_type' => $vendor->vendor_type,
                    'status' => $vendor->status,
                    'pan_last4' => $vendor->pan_last4,
                ],
                $request,
            );

            return $vendor;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateVendor(Vendor $vendor, array $data, User $actor, ?Request $request = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $actor, $request): Vendor {
            $lockedVendor = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $lockedVendor->company_id, 'vendor');

            $lockedVendor->forceFill($this->vendorPayload($data))->save();

            $this->auditLogger->record(
                $actor,
                'procurement.vendor.updated',
                'Updated vendor master record',
                $lockedVendor,
                [
                    'vendor_code' => $lockedVendor->vendor_code,
                    'vendor_type' => $lockedVendor->vendor_type,
                    'status' => $lockedVendor->status,
                    'changed_fields' => array_values(array_keys($data)),
                    'pan_last4' => $lockedVendor->pan_last4,
                ],
                $request,
            );

            return $lockedVendor->fresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateVendorStatus(Vendor $vendor, array $data, User $actor, ?Request $request = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $actor, $request): Vendor {
            $lockedVendor = Vendor::query()->whereKey($vendor->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $lockedVendor->company_id, 'vendor');

            $oldStatus = $lockedVendor->status;
            $history = $lockedVendor->metadata['status_history'] ?? [];
            $history[] = [
                'from' => $oldStatus,
                'to' => $data['status'],
                'reason' => $data['reason'] ?? null,
                'actor' => $actor->name,
                'at' => now()->toISOString(),
            ];

            $lockedVendor->forceFill([
                'status' => $data['status'],
                'metadata' => array_merge($lockedVendor->metadata ?? [], [
                    'status_history' => $history,
                    'last_status_reason' => $data['reason'] ?? null,
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'procurement.vendor.status_updated',
                'Updated vendor status',
                $lockedVendor,
                [
                    'vendor_code' => $lockedVendor->vendor_code,
                    'from_status' => $oldStatus,
                    'to_status' => $lockedVendor->status,
                    'reason' => $data['reason'] ?? null,
                ],
                $request,
            );

            return $lockedVendor->fresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function vendorPerformance(Vendor $vendor, User $actor): array
    {
        $this->assertCompanyScope($actor, $vendor->company_id, 'vendor');

        $purchaseOrders = PurchaseOrder::query()
            ->with(['project', 'goodsReceipts'])
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('po_date')
            ->get();

        $goodsReceipts = GoodsReceipt::query()
            ->with('purchaseOrder')
            ->whereIn('purchase_order_id', $purchaseOrders->pluck('id'))
            ->where('status', 'received')
            ->orderByDesc('received_on')
            ->get();

        $acceptedAmount = round((float) $goodsReceipts->sum(fn (GoodsReceipt $receipt): float => (float) $receipt->accepted_total), 2);
        $paidAmount = $this->vendorPaidAmount($vendor);
        $payableAmount = round(max(0, $acceptedAmount - $paidAmount), 2);
        $orderedQuantity = 0.0;
        $acceptedQuantity = 0.0;
        $rejectedQuantity = 0.0;

        foreach ($purchaseOrders as $purchaseOrder) {
            foreach ($purchaseOrder->items ?? [] as $item) {
                $orderedQuantity += (float) ($item['quantity'] ?? 0);
            }
        }

        foreach ($goodsReceipts as $receipt) {
            foreach ($receipt->items ?? [] as $item) {
                $acceptedQuantity += (float) ($item['accepted_quantity'] ?? 0);
                $rejectedQuantity += (float) ($item['rejected_quantity'] ?? 0);
            }
        }

        $receivedQuantity = $acceptedQuantity + $rejectedQuantity;
        $acceptanceRate = $receivedQuantity > 0 ? round(($acceptedQuantity / $receivedQuantity) * 100, 2) : null;
        $fulfillmentRate = $orderedQuantity > 0 ? round(($acceptedQuantity / $orderedQuantity) * 100, 2) : null;
        $onTimeDeliveryRate = $this->vendorOnTimeDeliveryRate($goodsReceipts);
        $ratingScore = $this->vendorRatingScore($acceptanceRate, $onTimeDeliveryRate);

        return [
            'vendor' => [
                'id' => $vendor->id,
                'vendor_code' => $vendor->vendor_code,
                'name' => $vendor->name,
                'vendor_type' => $vendor->vendor_type,
                'status' => $vendor->status,
            ],
            'summary' => [
                'purchase_orders' => $purchaseOrders->count(),
                'open_purchase_orders' => $purchaseOrders->whereIn('status', ['approved', 'partially_received'])->count(),
                'received_purchase_orders' => $purchaseOrders->where('status', 'received')->count(),
                'purchase_order_total' => round((float) $purchaseOrders->sum(fn (PurchaseOrder $order): float => (float) $order->total_amount), 2),
                'goods_receipts' => $goodsReceipts->count(),
                'accepted_amount' => $acceptedAmount,
                'paid_amount' => $paidAmount,
                'payable_amount' => $payableAmount,
                'ordered_quantity' => round($orderedQuantity, 3),
                'accepted_quantity' => round($acceptedQuantity, 3),
                'rejected_quantity' => round($rejectedQuantity, 3),
                'acceptance_rate_percent' => $acceptanceRate,
                'fulfillment_rate_percent' => $fulfillmentRate,
                'on_time_delivery_rate_percent' => $onTimeDeliveryRate,
                'rating_score' => $ratingScore,
                'rating_label' => $this->vendorRatingLabel($ratingScore),
            ],
            'purchase_history' => $purchaseOrders->map(fn (PurchaseOrder $order): array => [
                'id' => $order->id,
                'po_number' => $order->po_number,
                'project_id' => $order->project_id,
                'project_code' => $order->project?->code,
                'po_date' => $order->po_date?->toDateString(),
                'expected_delivery_on' => $order->expected_delivery_on?->toDateString(),
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'accepted_total' => round((float) $order->goodsReceipts->sum(fn (GoodsReceipt $receipt): float => (float) $receipt->accepted_total), 2),
                'goods_receipts' => $order->goodsReceipts->count(),
            ])->values()->all(),
            'goods_receipt_history' => $goodsReceipts->map(fn (GoodsReceipt $receipt): array => [
                'id' => $receipt->id,
                'grn_number' => $receipt->grn_number,
                'po_number' => $receipt->purchaseOrder?->po_number,
                'received_on' => $receipt->received_on?->toDateString(),
                'accepted_total' => (float) $receipt->accepted_total,
                'accepted_quantity' => round((float) collect($receipt->items ?? [])->sum(fn (array $item): float => (float) ($item['accepted_quantity'] ?? 0)), 3),
                'rejected_quantity' => round((float) collect($receipt->items ?? [])->sum(fn (array $item): float => (float) ($item['rejected_quantity'] ?? 0)), 3),
                'quality_notes' => $receipt->quality_notes,
            ])->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function submitRequisition(array $data, User $actor, ?Request $request = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $actor, $request): PurchaseRequisition {
            $project = Project::query()->whereKey($data['project_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $project->company_id, 'project_id');

            if ($project->status !== 'active') {
                throw ValidationException::withMessages(['project_id' => 'The selected project is not active for your company.']);
            }

            $items = $this->normalizeRequisitionItems($data['items']);
            $estimatedTotal = collect($items)->sum('estimated_amount');

            $requisition = PurchaseRequisition::create([
                'company_id' => $project->company_id,
                'project_id' => $project->id,
                'requested_by_user_id' => $actor->id,
                'requisition_number' => $this->nextRequisitionNumber(),
                'department' => $data['department'],
                'required_by' => $data['required_by'],
                'priority' => $data['priority'],
                'status' => 'submitted',
                'items' => $items,
                'estimated_total' => round((float) $estimatedTotal, 2),
                'purpose' => $data['purpose'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('submitted', $actor, 'Purchase requisition submitted'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'procurement.requisition.submitted',
                'Submitted purchase requisition',
                $requisition,
                [
                    'requisition_number' => $requisition->requisition_number,
                    'estimated_total' => $requisition->estimated_total,
                ],
                $request,
            );

            return $requisition->load($this->requisitionRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approveRequisition(PurchaseRequisition $purchaseRequisition, User $actor, array $data = [], ?Request $request = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($purchaseRequisition, $actor, $data, $request): PurchaseRequisition {
            $requisition = PurchaseRequisition::query()->whereKey($purchaseRequisition->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $requisition->company_id, 'requisition');

            if ($requisition->status !== 'submitted') {
                throw ValidationException::withMessages(['requisition' => 'Only submitted requisitions can be approved.']);
            }

            if ($requisition->requested_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['requisition' => 'The requester cannot approve the same requisition.']);
            }

            $history = $requisition->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Purchase requisition approved');

            $requisition->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'procurement.requisition.approved',
                'Approved purchase requisition',
                $requisition,
                ['requisition_number' => $requisition->requisition_number, 'note' => $data['note'] ?? null],
                $request,
            );

            return $requisition->load($this->requisitionRelations());
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function quoteComparison(PurchaseRequisition $purchaseRequisition, User $actor): array
    {
        $requisition = PurchaseRequisition::query()
            ->with(['project', 'requestedBy', 'approvedBy'])
            ->whereKey($purchaseRequisition->id)
            ->firstOrFail();

        $this->assertCompanyScope($actor, $requisition->company_id, 'purchase_requisition');

        $purchaseOrders = PurchaseOrder::query()
            ->with(['vendor', 'createdBy', 'approvedBy'])
            ->where('company_id', $requisition->company_id)
            ->where('purchase_requisition_id', $requisition->id)
            ->orderBy('total_amount')
            ->orderBy('po_date')
            ->get();

        $requisitionItems = collect($requisition->items ?? [])
            ->map(function (array $item): array {
                $quantity = round((float) ($item['quantity'] ?? 0), 3);
                $estimatedRate = round((float) ($item['estimated_rate'] ?? 0), 4);
                $estimatedAmount = round((float) ($item['estimated_amount'] ?? ($quantity * $estimatedRate)), 2);

                return [
                    'item_code' => strtoupper((string) ($item['item_code'] ?? '')),
                    'description' => (string) ($item['description'] ?? $item['item_code'] ?? 'Requested item'),
                    'unit' => (string) ($item['unit'] ?? 'unit'),
                    'quantity' => $quantity,
                    'estimated_rate' => $estimatedRate,
                    'estimated_amount' => $estimatedAmount,
                ];
            })
            ->filter(fn (array $item): bool => $item['item_code'] !== '')
            ->values();

        $candidateRows = $purchaseOrders
            ->map(function (PurchaseOrder $order): array {
                return [
                    'id' => $order->id,
                    'po_number' => $order->po_number,
                    'po_date' => $order->po_date?->toDateString(),
                    'expected_delivery_on' => $order->expected_delivery_on?->toDateString(),
                    'status' => $order->status,
                    'vendor' => $order->vendor ? [
                        'id' => $order->vendor->id,
                        'vendor_code' => $order->vendor->vendor_code,
                        'name' => $order->vendor->name,
                        'status' => $order->vendor->status,
                    ] : null,
                    'subtotal' => (float) $order->subtotal,
                    'tax_amount' => (float) $order->tax_amount,
                    'total_amount' => (float) $order->total_amount,
                    'payment_terms' => $order->payment_terms,
                    'terms' => $order->terms,
                    'items_count' => count($order->items ?? []),
                    'created_by' => $order->createdBy?->name,
                    'approved_by' => $order->approvedBy?->name,
                    'approved_at' => $order->approved_at?->toISOString(),
                ];
            })
            ->values();

        $lowestCandidate = $candidateRows->sortBy('total_amount')->first();

        return [
            'source' => 'laravel_purchase_requisition_purchase_orders',
            'requisition' => [
                'id' => $requisition->id,
                'requisition_number' => $requisition->requisition_number,
                'status' => $requisition->status,
                'department' => $requisition->department,
                'required_by' => $requisition->required_by?->toDateString(),
                'estimated_total' => (float) $requisition->estimated_total,
                'project' => $requisition->project ? [
                    'id' => $requisition->project->id,
                    'code' => $requisition->project->code,
                    'name' => $requisition->project->name,
                ] : null,
                'requested_by' => $requisition->requestedBy?->name,
                'approved_by' => $requisition->approvedBy?->name,
                'approved_at' => $requisition->approved_at?->toISOString(),
                'items' => $requisitionItems->all(),
            ],
            'summary' => [
                'candidate_count' => $candidateRows->count(),
                'lowest_total_amount' => $lowestCandidate['total_amount'] ?? null,
                'lowest_po_number' => $lowestCandidate['po_number'] ?? null,
                'lowest_vendor_name' => $lowestCandidate['vendor']['name'] ?? null,
                'estimated_total' => (float) $requisition->estimated_total,
                'variance_against_estimate' => $lowestCandidate
                    ? round((float) $lowestCandidate['total_amount'] - (float) $requisition->estimated_total, 2)
                    : null,
                'recommendation_status' => $candidateRows->isEmpty()
                    ? 'quotes_required'
                    : ($candidateRows->count() === 1 ? 'single_candidate' : 'ready_for_commercial_review'),
                'recommendation_note' => $candidateRows->isEmpty()
                    ? 'No linked purchase-order candidates are available for comparison. Capture vendor quotations or draft POs before commercial review.'
                    : 'Comparison is based only on purchase orders linked to this requisition and visible in the current company scope.',
            ],
            'candidates' => $candidateRows->all(),
            'item_comparison' => $this->quoteItemComparison($requisitionItems, $purchaseOrders),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createPurchaseOrder(array $data, User $actor, ?Request $request = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor, $request): PurchaseOrder {
            $requisition = isset($data['purchase_requisition_id'])
                ? PurchaseRequisition::query()->whereKey($data['purchase_requisition_id'])->lockForUpdate()->firstOrFail()
                : null;
            $vendor = Vendor::query()->whereKey($data['vendor_id'])->firstOrFail();

            $items = $this->normalizePurchaseOrderItems($data['items']);
            $subtotal = collect($items)->sum('line_amount');
            $taxAmount = collect($items)->sum('tax_amount');
            $projectId = $requisition?->project_id ?? $data['project_id'] ?? null;

            if (! $projectId) {
                throw ValidationException::withMessages(['project_id' => 'Project is required when no purchase requisition is selected.']);
            }

            $project = Project::query()->whereKey($projectId)->firstOrFail();
            $companyId = $requisition?->company_id ?? $project->company_id;

            $this->assertCompanyScope($actor, $companyId, $requisition ? 'purchase_requisition_id' : 'project_id');

            if ($vendor->company_id !== $companyId) {
                throw ValidationException::withMessages(['vendor_id' => 'The selected vendor is not available for the purchase order company.']);
            }

            if ($vendor->status !== 'active') {
                throw ValidationException::withMessages(['vendor_id' => 'The selected vendor is not active for your company.']);
            }

            if ($project->company_id !== $companyId || $project->status !== 'active') {
                throw ValidationException::withMessages(['project_id' => 'Project is required and must be active for purchase orders.']);
            }

            if ($requisition && $requisition->status !== 'approved') {
                throw ValidationException::withMessages(['purchase_requisition_id' => 'Purchase orders can be created only from an approved requisition.']);
            }

            $purchaseOrder = PurchaseOrder::create([
                'company_id' => $companyId,
                'project_id' => $projectId,
                'purchase_requisition_id' => $requisition?->id,
                'vendor_id' => $vendor->id,
                'created_by_user_id' => $actor->id,
                'po_number' => $this->nextPurchaseOrderNumber(),
                'po_date' => $data['po_date'],
                'expected_delivery_on' => $data['expected_delivery_on'] ?? null,
                'status' => 'draft',
                'payment_terms' => $data['payment_terms'] ?? null,
                'items' => $items,
                'subtotal' => round((float) $subtotal, 2),
                'tax_amount' => round((float) $taxAmount, 2),
                'total_amount' => round((float) $subtotal + (float) $taxAmount, 2),
                'terms' => $data['terms'] ?? null,
                'workflow_history' => [
                    $this->workflowEvent('draft', $actor, 'Purchase order drafted'),
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'procurement.purchase_order.created',
                'Created purchase order draft',
                $purchaseOrder,
                [
                    'po_number' => $purchaseOrder->po_number,
                    'total_amount' => $purchaseOrder->total_amount,
                    'vendor_id' => $purchaseOrder->vendor_id,
                ],
                $request,
            );

            return $purchaseOrder->load($this->purchaseOrderRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, User $actor, array $data = [], ?Request $request = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $data, $request): PurchaseOrder {
            $order = PurchaseOrder::query()->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();
            $this->assertCompanyScope($actor, $order->company_id, 'purchase_order');

            if ($order->status !== 'draft') {
                throw ValidationException::withMessages(['purchase_order' => 'Only draft purchase orders can be approved.']);
            }

            if ($order->created_by_user_id === $actor->id) {
                throw ValidationException::withMessages(['purchase_order' => 'The purchase order creator cannot approve the same order.']);
            }

            $history = $order->workflow_history ?? [];
            $history[] = $this->workflowEvent('approved', $actor, $data['note'] ?? 'Purchase order approved');

            $order->forceFill([
                'status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'procurement.purchase_order.approved',
                'Approved purchase order',
                $order,
                ['po_number' => $order->po_number, 'total_amount' => $order->total_amount, 'note' => $data['note'] ?? null],
                $request,
            );

            return $order->load($this->purchaseOrderRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function receiveGoods(array $data, User $actor, ?Request $request = null): GoodsReceipt
    {
        return DB::transaction(function () use ($data, $actor, $request): GoodsReceipt {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($data['purchase_order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $purchaseOrder->company_id, 'purchase_order_id');

            if (! in_array($purchaseOrder->status, ['approved', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order_id' => 'Goods can be received only against approved purchase orders.']);
            }

            $items = $this->normalizeReceiptItems($purchaseOrder, $data['items']);
            $acceptedTotal = collect($items)->sum('accepted_amount');

            $receipt = GoodsReceipt::create([
                'company_id' => $purchaseOrder->company_id,
                'project_id' => $purchaseOrder->project_id,
                'purchase_order_id' => $purchaseOrder->id,
                'received_by_user_id' => $actor->id,
                'grn_number' => $this->nextGoodsReceiptNumber(),
                'received_on' => $data['received_on'],
                'delivery_challan_number' => $data['delivery_challan_number'] ?? null,
                'status' => 'received',
                'items' => $items,
                'accepted_total' => round((float) $acceptedTotal, 2),
                'quality_notes' => $data['quality_notes'] ?? null,
                'metadata' => ['source' => 'procurement_service'],
            ]);

            $this->postGoodsReceiptStockMovements($receipt, $purchaseOrder, $actor);

            $purchaseOrder->forceFill([
                'status' => $this->purchaseOrderReceiptStatus($purchaseOrder),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'procurement.goods_receipt.created',
                'Created goods receipt',
                $receipt,
                [
                    'grn_number' => $receipt->grn_number,
                    'po_number' => $purchaseOrder->po_number,
                    'accepted_total' => $receipt->accepted_total,
                ],
                $request,
            );

            return $receipt->load($this->goodsReceiptRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function issueStock(array $data, User $actor, ?Request $request = null): StockMovement
    {
        return DB::transaction(function () use ($data, $actor, $request): StockMovement {
            $stockItem = StockItem::query()
                ->whereKey($data['stock_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $stockItem->company_id, 'stock_item_id');

            if ($stockItem->status !== 'active') {
                throw ValidationException::withMessages(['stock_item_id' => 'Only active stock items can be issued.']);
            }

            $quantity = round((float) $data['quantity'], 3);
            $availableQuantity = round((float) $stockItem->on_hand_quantity, 3);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Issued quantity must be greater than zero.']);
            }

            if ($quantity > $availableQuantity) {
                throw ValidationException::withMessages(['quantity' => 'Issued quantity cannot exceed available stock.']);
            }

            $rate = round((float) $stockItem->average_rate, 4);
            $amount = round($quantity * $rate, 2);
            $newQuantity = round($availableQuantity - $quantity, 3);
            $newValue = $newQuantity <= 0 ? 0 : round(max(0, (float) $stockItem->stock_value - $amount), 2);
            $newAverageRate = $newQuantity > 0 ? round($newValue / $newQuantity, 4) : 0;
            $sequence = $this->nextStockMovementSequence();

            $stockItem->forceFill([
                'on_hand_quantity' => $newQuantity,
                'stock_value' => $newValue,
                'average_rate' => $newAverageRate,
                'last_movement_at' => now(),
            ])->save();

            $movement = StockMovement::create([
                'company_id' => $stockItem->company_id,
                'project_id' => $stockItem->project_id,
                'stock_item_id' => $stockItem->id,
                'purchase_order_id' => null,
                'goods_receipt_id' => null,
                'created_by_user_id' => $actor->id,
                'movement_number' => sprintf('STM-%04d', $sequence),
                'movement_type' => $data['movement_type'],
                'movement_date' => $data['movement_date'],
                'store_type' => $stockItem->store_type,
                'item_code' => $stockItem->item_code,
                'description' => $stockItem->description,
                'unit' => $stockItem->unit,
                'quantity' => -1 * $quantity,
                'rate' => $rate,
                'amount' => -1 * $amount,
                'balance_after_quantity' => $newQuantity,
                'balance_after_value' => $newValue,
                'source_type' => 'stock_issue',
                'source_id' => $sequence,
                'metadata' => [
                    'issue_reference' => $data['issue_reference'] ?? null,
                    'purpose' => $data['purpose'],
                    'remarks' => $data['remarks'] ?? null,
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'procurement.stock_movement.issued',
                'Issued stock from store',
                $movement,
                [
                    'movement_number' => $movement->movement_number,
                    'movement_type' => $movement->movement_type,
                    'item_code' => $movement->item_code,
                    'quantity' => $quantity,
                    'balance_after_quantity' => $newQuantity,
                    'purpose' => $data['purpose'],
                ],
                $request,
            );

            $this->sendLowStockAlertIfNeeded($stockItem, $movement, $actor);

            return $movement->load(['stockItem', 'purchaseOrder', 'goodsReceipt']);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function returnStock(array $data, User $actor, ?Request $request = null): StockMovement
    {
        return DB::transaction(function () use ($data, $actor, $request): StockMovement {
            $stockItem = StockItem::query()
                ->whereKey($data['stock_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $stockItem->company_id, 'stock_item_id');

            if ($stockItem->status !== 'active') {
                throw ValidationException::withMessages(['stock_item_id' => 'Only active stock items can accept returns.']);
            }

            $quantity = round((float) $data['quantity'], 3);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Return quantity must be greater than zero.']);
            }

            $rate = round((float) $stockItem->average_rate, 4);
            $amount = round($quantity * $rate, 2);
            $newQuantity = round((float) $stockItem->on_hand_quantity + $quantity, 3);
            $newValue = round((float) $stockItem->stock_value + $amount, 2);
            $newAverageRate = $newQuantity > 0 ? round($newValue / $newQuantity, 4) : 0;
            $sequence = $this->nextStockMovementSequence();

            $stockItem->forceFill([
                'on_hand_quantity' => $newQuantity,
                'stock_value' => $newValue,
                'average_rate' => $newAverageRate,
                'last_movement_at' => now(),
            ])->save();

            $movement = StockMovement::create([
                'company_id' => $stockItem->company_id,
                'project_id' => $stockItem->project_id,
                'stock_item_id' => $stockItem->id,
                'purchase_order_id' => null,
                'goods_receipt_id' => null,
                'created_by_user_id' => $actor->id,
                'movement_number' => sprintf('STM-%04d', $sequence),
                'movement_type' => 'return',
                'movement_date' => $data['movement_date'],
                'store_type' => $stockItem->store_type,
                'item_code' => $stockItem->item_code,
                'description' => $stockItem->description,
                'unit' => $stockItem->unit,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount,
                'balance_after_quantity' => $newQuantity,
                'balance_after_value' => $newValue,
                'source_type' => 'stock_return',
                'source_id' => $sequence,
                'metadata' => [
                    'return_reference' => $data['return_reference'] ?? null,
                    'returned_from' => $data['returned_from'] ?? null,
                    'reason' => $data['reason'],
                    'remarks' => $data['remarks'] ?? null,
                ],
            ]);

            $this->auditLogger->record(
                $actor,
                'procurement.stock_movement.returned',
                'Returned stock to store',
                $movement,
                [
                    'movement_number' => $movement->movement_number,
                    'item_code' => $movement->item_code,
                    'quantity' => $quantity,
                    'balance_after_quantity' => $newQuantity,
                    'reason' => $data['reason'],
                ],
                $request,
            );

            $this->sendLowStockAlertIfNeeded($stockItem, $movement, $actor);

            return $movement->load(['stockItem', 'purchaseOrder', 'goodsReceipt']);
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return Collection<int, StockMovement>
     */
    public function transferStock(array $data, User $actor, ?Request $request = null): Collection
    {
        return DB::transaction(function () use ($data, $actor, $request): Collection {
            $sourceItem = StockItem::query()
                ->whereKey($data['source_stock_item_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $sourceItem->company_id, 'source_stock_item_id');

            if ($sourceItem->status !== 'active') {
                throw ValidationException::withMessages(['source_stock_item_id' => 'Only active stock items can be transferred.']);
            }

            $destinationProject = Project::query()->whereKey($data['destination_project_id'])->firstOrFail();
            $this->assertCompanyScope($actor, $destinationProject->company_id, 'destination_project_id');

            if ((int) $destinationProject->company_id !== (int) $sourceItem->company_id) {
                throw ValidationException::withMessages(['destination_project_id' => 'Stock transfers must remain within the same company.']);
            }

            if ($destinationProject->status !== 'active') {
                throw ValidationException::withMessages(['destination_project_id' => 'The selected destination project is not active.']);
            }

            if ((int) $sourceItem->project_id === (int) $destinationProject->id && $sourceItem->store_type === (string) $data['destination_store_type']) {
                throw ValidationException::withMessages(['destination_project_id' => 'Destination project and store must differ from the source stock location.']);
            }

            $quantity = round((float) $data['quantity'], 3);
            $availableQuantity = round((float) $sourceItem->on_hand_quantity, 3);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['quantity' => 'Transfer quantity must be greater than zero.']);
            }

            if ($quantity > $availableQuantity) {
                throw ValidationException::withMessages(['quantity' => 'Transfer quantity cannot exceed available stock.']);
            }

            $destinationItem = StockItem::query()
                ->where('company_id', $sourceItem->company_id)
                ->where('project_id', $destinationProject->id)
                ->where('store_type', $data['destination_store_type'])
                ->where('item_code', $sourceItem->item_code)
                ->lockForUpdate()
                ->first();

            if ($destinationItem && $destinationItem->status !== 'active') {
                throw ValidationException::withMessages(['destination_project_id' => 'Destination stock item is inactive.']);
            }

            if (! $destinationItem) {
                $destinationItem = StockItem::create([
                    'company_id' => $sourceItem->company_id,
                    'project_id' => $destinationProject->id,
                    'store_type' => $data['destination_store_type'],
                    'item_code' => $sourceItem->item_code,
                    'description' => $sourceItem->description,
                    'unit' => $sourceItem->unit,
                    'on_hand_quantity' => 0,
                    'stock_value' => 0,
                    'average_rate' => 0,
                    'minimum_stock_quantity' => $sourceItem->minimum_stock_quantity,
                    'status' => 'active',
                    'metadata' => [
                        'source' => 'stock_transfer',
                        'source_stock_item_id' => $sourceItem->id,
                    ],
                ]);
            }

            $rate = round((float) $sourceItem->average_rate, 4);
            $amount = round($quantity * $rate, 2);
            $sourceNewQuantity = round($availableQuantity - $quantity, 3);
            $sourceNewValue = $sourceNewQuantity <= 0 ? 0 : round(max(0, (float) $sourceItem->stock_value - $amount), 2);
            $sourceNewAverageRate = $sourceNewQuantity > 0 ? round($sourceNewValue / $sourceNewQuantity, 4) : 0;
            $destinationNewQuantity = round((float) $destinationItem->on_hand_quantity + $quantity, 3);
            $destinationNewValue = round((float) $destinationItem->stock_value + $amount, 2);
            $destinationNewAverageRate = $destinationNewQuantity > 0 ? round($destinationNewValue / $destinationNewQuantity, 4) : 0;
            $transferSequence = $this->nextStockMovementSequence();

            $sourceItem->forceFill([
                'on_hand_quantity' => $sourceNewQuantity,
                'stock_value' => $sourceNewValue,
                'average_rate' => $sourceNewAverageRate,
                'last_movement_at' => now(),
            ])->save();

            $destinationItem->forceFill([
                'description' => $sourceItem->description,
                'unit' => $sourceItem->unit,
                'on_hand_quantity' => $destinationNewQuantity,
                'stock_value' => $destinationNewValue,
                'average_rate' => $destinationNewAverageRate,
                'last_movement_at' => now(),
            ])->save();

            $metadata = [
                'transfer_reference' => $data['transfer_reference'] ?? null,
                'purpose' => $data['purpose'],
                'remarks' => $data['remarks'] ?? null,
                'source_stock_item_id' => $sourceItem->id,
                'destination_stock_item_id' => $destinationItem->id,
                'source_project_id' => $sourceItem->project_id,
                'destination_project_id' => $destinationProject->id,
                'source_store_type' => $sourceItem->store_type,
                'destination_store_type' => $destinationItem->store_type,
            ];

            $outMovement = StockMovement::create([
                'company_id' => $sourceItem->company_id,
                'project_id' => $sourceItem->project_id,
                'stock_item_id' => $sourceItem->id,
                'purchase_order_id' => null,
                'goods_receipt_id' => null,
                'created_by_user_id' => $actor->id,
                'movement_number' => sprintf('STM-%04d', $transferSequence),
                'movement_type' => 'transfer_out',
                'movement_date' => $data['movement_date'],
                'store_type' => $sourceItem->store_type,
                'item_code' => $sourceItem->item_code,
                'description' => $sourceItem->description,
                'unit' => $sourceItem->unit,
                'quantity' => -1 * $quantity,
                'rate' => $rate,
                'amount' => -1 * $amount,
                'balance_after_quantity' => $sourceNewQuantity,
                'balance_after_value' => $sourceNewValue,
                'source_type' => 'stock_transfer',
                'source_id' => $transferSequence,
                'metadata' => $metadata,
            ]);

            $inMovement = StockMovement::create([
                'company_id' => $destinationItem->company_id,
                'project_id' => $destinationItem->project_id,
                'stock_item_id' => $destinationItem->id,
                'purchase_order_id' => null,
                'goods_receipt_id' => null,
                'created_by_user_id' => $actor->id,
                'movement_number' => sprintf('STM-%04d', $transferSequence + 1),
                'movement_type' => 'transfer_in',
                'movement_date' => $data['movement_date'],
                'store_type' => $destinationItem->store_type,
                'item_code' => $destinationItem->item_code,
                'description' => $destinationItem->description,
                'unit' => $destinationItem->unit,
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount,
                'balance_after_quantity' => $destinationNewQuantity,
                'balance_after_value' => $destinationNewValue,
                'source_type' => 'stock_transfer',
                'source_id' => $transferSequence,
                'metadata' => $metadata,
            ]);

            $this->auditLogger->record(
                $actor,
                'procurement.stock_movement.transferred',
                'Transferred stock between stores',
                $outMovement,
                [
                    'source_movement_number' => $outMovement->movement_number,
                    'destination_movement_number' => $inMovement->movement_number,
                    'item_code' => $sourceItem->item_code,
                    'quantity' => $quantity,
                    'source_project_id' => $sourceItem->project_id,
                    'destination_project_id' => $destinationProject->id,
                ],
                $request,
            );

            $this->sendLowStockAlertIfNeeded($sourceItem, $outMovement, $actor);
            $this->sendLowStockAlertIfNeeded($destinationItem, $inMovement, $actor);

            return collect([
                $outMovement->load(['stockItem', 'purchaseOrder', 'goodsReceipt']),
                $inMovement->load(['stockItem', 'purchaseOrder', 'goodsReceipt']),
            ]);
        });
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRequisitionItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $quantity = (float) $item['quantity'];
            $rate = (float) $item['estimated_rate'];

            return [
                'item_code' => $item['item_code'],
                'description' => $item['description'],
                'unit' => $item['unit'],
                'quantity' => $quantity,
                'estimated_rate' => $rate,
                'estimated_amount' => round($quantity * $rate, 2),
            ];
        })->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizePurchaseOrderItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $quantity = (float) $item['quantity'];
            $rate = (float) $item['rate'];
            $taxRate = (float) $item['tax_rate'];
            $lineAmount = round($quantity * $rate, 2);
            $taxAmount = round($lineAmount * ($taxRate / 100), 2);

            return [
                'item_code' => $item['item_code'],
                'description' => $item['description'],
                'unit' => $item['unit'],
                'quantity' => $quantity,
                'rate' => $rate,
                'tax_rate' => $taxRate,
                'line_amount' => $lineAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => round($lineAmount + $taxAmount, 2),
            ];
        })->values()->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $requisitionItems
     * @param Collection<int, PurchaseOrder> $purchaseOrders
     * @return array<int, array<string, mixed>>
     */
    private function quoteItemComparison(Collection $requisitionItems, Collection $purchaseOrders): array
    {
        return $requisitionItems
            ->map(function (array $requestedItem) use ($purchaseOrders): array {
                $itemCode = strtoupper((string) $requestedItem['item_code']);

                $quoteLines = $purchaseOrders
                    ->map(function (PurchaseOrder $order) use ($itemCode): ?array {
                        $line = collect($order->items ?? [])
                            ->first(fn (array $candidate): bool => strtoupper((string) ($candidate['item_code'] ?? '')) === $itemCode);

                        if (! is_array($line)) {
                            return null;
                        }

                        return [
                            'purchase_order_id' => $order->id,
                            'po_number' => $order->po_number,
                            'vendor_id' => $order->vendor_id,
                            'vendor_name' => $order->vendor?->name,
                            'status' => $order->status,
                            'quantity' => round((float) ($line['quantity'] ?? 0), 3),
                            'rate' => round((float) ($line['rate'] ?? 0), 4),
                            'tax_rate' => round((float) ($line['tax_rate'] ?? 0), 4),
                            'line_amount' => round((float) ($line['line_amount'] ?? 0), 2),
                            'tax_amount' => round((float) ($line['tax_amount'] ?? 0), 2),
                            'total_amount' => round((float) ($line['total_amount'] ?? 0), 2),
                        ];
                    })
                    ->filter()
                    ->values();

                $lowest = $quoteLines->sortBy('rate')->first();

                return [
                    'item_code' => $itemCode,
                    'description' => $requestedItem['description'],
                    'unit' => $requestedItem['unit'],
                    'requested_quantity' => $requestedItem['quantity'],
                    'estimated_rate' => $requestedItem['estimated_rate'],
                    'estimated_amount' => $requestedItem['estimated_amount'],
                    'quote_count' => $quoteLines->count(),
                    'lowest_rate' => $lowest['rate'] ?? null,
                    'lowest_vendor_name' => $lowest['vendor_name'] ?? null,
                    'rate_variance_against_estimate' => $lowest
                        ? round((float) $lowest['rate'] - (float) $requestedItem['estimated_rate'], 4)
                        : null,
                    'quotes' => $quoteLines->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeReceiptItems(PurchaseOrder $purchaseOrder, array $items): array
    {
        $orderedItems = collect($purchaseOrder->items ?? [])->keyBy('item_code');
        $alreadyReceived = $this->receivedQuantities($purchaseOrder);
        $seenItemCodes = [];

        return collect($items)->map(function (array $item) use ($orderedItems, $alreadyReceived, &$seenItemCodes): array {
            $itemCode = strtoupper((string) $item['item_code']);

            if (in_array($itemCode, $seenItemCodes, true)) {
                throw ValidationException::withMessages(['items' => "Duplicate goods receipt line found for {$itemCode}."]);
            }

            $seenItemCodes[] = $itemCode;
            $ordered = $orderedItems->get($itemCode);

            if (! $ordered) {
                throw ValidationException::withMessages(['items' => "Item {$itemCode} is not part of the selected purchase order."]);
            }

            $acceptedQuantity = (float) $item['accepted_quantity'];
            $rejectedQuantity = (float) ($item['rejected_quantity'] ?? 0);
            $previousAccepted = (float) ($alreadyReceived[$itemCode] ?? 0);
            $orderedQuantity = (float) $ordered['quantity'];

            if (($previousAccepted + $acceptedQuantity) > $orderedQuantity) {
                throw ValidationException::withMessages(['items' => "Accepted quantity for {$itemCode} exceeds pending purchase order quantity."]);
            }

            $rate = (float) $ordered['rate'];
            $acceptedAmount = round($acceptedQuantity * $rate, 2);

            return [
                'item_code' => $itemCode,
                'description' => $ordered['description'],
                'unit' => $ordered['unit'],
                'ordered_quantity' => $orderedQuantity,
                'previous_accepted_quantity' => $previousAccepted,
                'accepted_quantity' => $acceptedQuantity,
                'rejected_quantity' => $rejectedQuantity,
                'rate' => $rate,
                'accepted_amount' => $acceptedAmount,
                'remarks' => $item['remarks'] ?? null,
            ];
        })->values()->all();
    }

    private function postGoodsReceiptStockMovements(GoodsReceipt $receipt, PurchaseOrder $purchaseOrder, User $actor): void
    {
        foreach ($receipt->items ?? [] as $item) {
            $itemCode = strtoupper((string) $item['item_code']);

            if (StockMovement::query()
                ->where('source_type', 'goods_receipt')
                ->where('source_id', $receipt->id)
                ->where('item_code', $itemCode)
                ->where('movement_type', 'inward')
                ->exists()) {
                continue;
            }

            $stockItem = StockItem::query()
                ->where('company_id', $receipt->company_id)
                ->where('project_id', $receipt->project_id)
                ->where('store_type', 'site')
                ->where('item_code', $itemCode)
                ->lockForUpdate()
                ->first();

            if (! $stockItem) {
                $stockItem = StockItem::create([
                    'company_id' => $receipt->company_id,
                    'project_id' => $receipt->project_id,
                    'store_type' => 'site',
                    'item_code' => $itemCode,
                    'description' => $item['description'],
                    'unit' => $item['unit'],
                    'on_hand_quantity' => 0,
                    'stock_value' => 0,
                    'average_rate' => 0,
                    'minimum_stock_quantity' => 0,
                    'status' => 'active',
                    'metadata' => ['source' => 'goods_receipt'],
                ]);
            }

            $quantity = (float) $item['accepted_quantity'];
            $rate = (float) $item['rate'];
            $amount = round($quantity * $rate, 2);
            $newQuantity = round((float) $stockItem->on_hand_quantity + $quantity, 3);
            $newValue = round((float) $stockItem->stock_value + $amount, 2);
            $averageRate = $newQuantity > 0 ? round($newValue / $newQuantity, 4) : 0;

            $stockItem->forceFill([
                'description' => $item['description'],
                'unit' => $item['unit'],
                'on_hand_quantity' => $newQuantity,
                'stock_value' => $newValue,
                'average_rate' => $averageRate,
                'status' => 'active',
                'last_movement_at' => now(),
            ])->save();

            $movement = StockMovement::create([
                'company_id' => $receipt->company_id,
                'project_id' => $receipt->project_id,
                'stock_item_id' => $stockItem->id,
                'purchase_order_id' => $purchaseOrder->id,
                'goods_receipt_id' => $receipt->id,
                'created_by_user_id' => $actor->id,
                'movement_number' => $this->nextStockMovementNumber(),
                'movement_type' => 'inward',
                'movement_date' => $receipt->received_on,
                'store_type' => 'site',
                'item_code' => $itemCode,
                'description' => $item['description'],
                'unit' => $item['unit'],
                'quantity' => $quantity,
                'rate' => $rate,
                'amount' => $amount,
                'balance_after_quantity' => $newQuantity,
                'balance_after_value' => $newValue,
                'source_type' => 'goods_receipt',
                'source_id' => $receipt->id,
                'metadata' => [
                    'grn_number' => $receipt->grn_number,
                    'po_number' => $purchaseOrder->po_number,
                    'delivery_challan_number' => $receipt->delivery_challan_number,
                ],
            ]);

            $this->sendLowStockAlertIfNeeded($stockItem, $movement, $actor);
        }
    }

    /**
     * @return array<string, float>
     */
    private function receivedQuantities(PurchaseOrder $purchaseOrder): array
    {
        $quantities = [];

        GoodsReceipt::query()
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('status', 'received')
            ->get()
            ->each(function (GoodsReceipt $receipt) use (&$quantities): void {
                foreach ($receipt->items ?? [] as $item) {
                    $itemCode = $item['item_code'];
                    $quantities[$itemCode] = ($quantities[$itemCode] ?? 0) + (float) $item['accepted_quantity'];
                }
            });

        return $quantities;
    }

    private function purchaseOrderReceiptStatus(PurchaseOrder $purchaseOrder): string
    {
        $orderedItems = collect($purchaseOrder->items ?? []);
        $received = $this->receivedQuantities($purchaseOrder);

        $allReceived = $orderedItems->every(fn (array $item): bool => (float) ($received[$item['item_code']] ?? 0) >= (float) $item['quantity']);

        return $allReceived ? 'received' : 'partially_received';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function companyIdForVendorWrite(array $data, User $actor): int
    {
        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : $actor->company_id;

        $this->assertCompanyScope($actor, $companyId, 'company_id');

        $company = Company::query()->whereKey($companyId)->first();

        if (! $company || $company->status !== 'active') {
            throw ValidationException::withMessages(['company_id' => 'The selected company is not active.']);
        }

        return (int) $company->id;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function vendorPayload(array $data, array $base = []): array
    {
        foreach ([
            'name',
            'vendor_type',
            'contact_name',
            'email',
            'phone',
            'gstin',
            'pan',
            'address',
            'bank_details',
            'compliance_documents',
            'status',
            'metadata',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $base[$field] = $this->normalizeVendorField($field, $data[$field]);
            }
        }

        return $base;
    }

    private function normalizeVendorField(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'gstin', 'pan' => strtoupper(trim((string) $value)),
            'email' => strtolower(trim((string) $value)),
            'phone', 'contact_name', 'name' => trim((string) $value),
            'bank_details' => $this->normalizeBankDetails(is_array($value) ? $value : []),
            default => $value,
        };
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function normalizeBankDetails(array $details): array
    {
        if (isset($details['ifsc']) && is_scalar($details['ifsc'])) {
            $details['ifsc'] = strtoupper(trim((string) $details['ifsc']));
        }

        return $details;
    }

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function nextRequisitionNumber(): string
    {
        return sprintf('PR-%04d', PurchaseRequisition::query()->withTrashed()->count() + 1001);
    }

    private function nextPurchaseOrderNumber(): string
    {
        return sprintf('PO-%04d', PurchaseOrder::query()->withTrashed()->count() + 1001);
    }

    private function nextGoodsReceiptNumber(): string
    {
        return sprintf('GRN-%04d', GoodsReceipt::query()->withTrashed()->count() + 1001);
    }

    private function nextStockMovementNumber(): string
    {
        return sprintf('STM-%04d', $this->nextStockMovementSequence());
    }

    private function nextStockMovementSequence(): int
    {
        return StockMovement::query()->count() + 1001;
    }

    private function vendorPaidAmount(Vendor $vendor): float
    {
        $voucherIds = FinancialVoucher::query()
            ->where('company_id', $vendor->company_id)
            ->where('status', 'approved')
            ->pluck('id');

        if ($voucherIds->isEmpty()) {
            return 0.0;
        }

        return round((float) FinancialVoucherLine::query()
            ->whereIn('financial_voucher_id', $voucherIds)
            ->where('party_type', Vendor::class)
            ->where('party_id', $vendor->id)
            ->where('line_type', 'debit')
            ->sum('amount'), 2);
    }

    /**
     * @param Collection<int, GoodsReceipt> $goodsReceipts
     */
    private function vendorOnTimeDeliveryRate(Collection $goodsReceipts): ?float
    {
        if ($goodsReceipts->isEmpty()) {
            return null;
        }

        $withExpectedDate = $goodsReceipts->filter(fn (GoodsReceipt $receipt): bool => $receipt->purchaseOrder?->expected_delivery_on !== null);

        if ($withExpectedDate->isEmpty()) {
            return null;
        }

        $onTimeCount = $withExpectedDate->filter(
            fn (GoodsReceipt $receipt): bool => $receipt->received_on !== null
                && $receipt->purchaseOrder?->expected_delivery_on !== null
                && $receipt->received_on->lessThanOrEqualTo($receipt->purchaseOrder->expected_delivery_on)
        )->count();

        return round(($onTimeCount / $withExpectedDate->count()) * 100, 2);
    }

    private function vendorRatingScore(?float $acceptanceRate, ?float $onTimeDeliveryRate): ?float
    {
        $scores = collect([$acceptanceRate, $onTimeDeliveryRate])
            ->filter(fn (?float $score): bool => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return round(((float) $scores->avg()) / 20, 2);
    }

    private function vendorRatingLabel(?float $ratingScore): ?string
    {
        if ($ratingScore === null) {
            return null;
        }

        if ($ratingScore >= 4.5) {
            return 'Excellent';
        }

        if ($ratingScore >= 3.5) {
            return 'Good';
        }

        if ($ratingScore >= 2.5) {
            return 'Needs Monitoring';
        }

        return 'At Risk';
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param array<string, mixed> $filters
     */
    private function applyProcurementDashboardFilters($query, array $filters, string $dateColumn, bool $supportsVendor = false): void
    {
        $query
            ->when($filters['project_id'] ?? null, fn ($builder, int $projectId) => $builder->where('project_id', $projectId))
            ->when($filters['date_from'] ?? null, fn ($builder, string $dateFrom) => $builder->whereDate($dateColumn, '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($builder, string $dateTo) => $builder->whereDate($dateColumn, '<=', $dateTo));

        if ($supportsVendor) {
            $query->when($filters['vendor_id'] ?? null, fn ($builder, int $vendorId) => $builder->where('vendor_id', $vendorId));
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<StockItem> $query
     * @param array<string, mixed> $filters
     */
    private function applyStockDashboardFilters($query, array $filters): void
    {
        $query
            ->when($filters['project_id'] ?? null, fn ($builder, int $projectId) => $builder->where('project_id', $projectId))
            ->where('status', 'active');
    }

    /**
     * @param Collection<int, PurchaseOrder> $purchaseOrders
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingDeliveries(Collection $purchaseOrders): Collection
    {
        return $purchaseOrders
            ->filter(fn (PurchaseOrder $order): bool => in_array($order->status, ['approved', 'partially_received'], true))
            ->flatMap(function (PurchaseOrder $order): array {
                $receivedQuantities = $this->receivedQuantities($order);

                return collect($order->items ?? [])
                    ->map(function (array $item) use ($order, $receivedQuantities): ?array {
                        $itemCode = strtoupper((string) $item['item_code']);
                        $orderedQuantity = round((float) ($item['quantity'] ?? 0), 3);
                        $receivedQuantity = round((float) ($receivedQuantities[$itemCode] ?? 0), 3);
                        $pendingQuantity = round(max(0, $orderedQuantity - $receivedQuantity), 3);

                        if ($pendingQuantity <= 0) {
                            return null;
                        }

                        $rate = round((float) ($item['rate'] ?? 0), 4);
                        $expectedDeliveryOn = $order->expected_delivery_on;

                        return [
                            'purchase_order_id' => $order->id,
                            'po_number' => $order->po_number,
                            'project_id' => $order->project_id,
                            'project_code' => $order->project?->code,
                            'vendor_id' => $order->vendor_id,
                            'vendor_name' => $order->vendor?->name,
                            'expected_delivery_on' => $expectedDeliveryOn?->toDateString(),
                            'is_overdue' => $expectedDeliveryOn !== null && $expectedDeliveryOn->isPast() && ! $expectedDeliveryOn->isToday(),
                            'item_code' => $itemCode,
                            'description' => (string) ($item['description'] ?? $itemCode),
                            'unit' => (string) ($item['unit'] ?? 'unit'),
                            'ordered_quantity' => $orderedQuantity,
                            'received_quantity' => $receivedQuantity,
                            'pending_quantity' => $pendingQuantity,
                            'rate' => $rate,
                            'pending_amount' => round($pendingQuantity * $rate, 2),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();
            })
            ->values();
    }

    private function sendLowStockAlertIfNeeded(StockItem $stockItem, StockMovement $movement, User $actor): void
    {
        $stockItem->refresh();

        if (! $stockItem->isBelowMinimum()) {
            return;
        }

        $existingUnreadAlert = UserNotification::query()
            ->where('company_id', $stockItem->company_id)
            ->where('category', 'procurement')
            ->where('status', 'unread')
            ->where('notifiable_type', StockItem::class)
            ->where('notifiable_id', $stockItem->id)
            ->where('payload->alert_type', 'low_stock')
            ->exists();

        if ($existingUnreadAlert) {
            return;
        }

        $this->notifications->sendToPermission(['procurement.manage', 'procurement.approve'], [
            'category' => 'procurement',
            'severity' => 'warning',
            'title' => "Low stock alert: {$stockItem->item_code}",
            'body' => "{$stockItem->description} is at {$stockItem->on_hand_quantity} {$stockItem->unit}, at or below the minimum level of {$stockItem->minimum_stock_quantity} {$stockItem->unit}.",
            'action_url' => route('procurement.stock-items.index', ['low_stock' => true, 'item_code' => $stockItem->item_code], false),
            'payload' => [
                'alert_type' => 'low_stock',
                'stock_item_id' => $stockItem->id,
                'movement_number' => $movement->movement_number,
                'movement_type' => $movement->movement_type,
                'project_id' => $stockItem->project_id,
                'store_type' => $stockItem->store_type,
                'item_code' => $stockItem->item_code,
                'on_hand_quantity' => (float) $stockItem->on_hand_quantity,
                'minimum_stock_quantity' => (float) $stockItem->minimum_stock_quantity,
            ],
        ], $actor, $stockItem, $stockItem->company_id);
    }

    /**
     * @return array<int, string>
     */
    private function requisitionRelations(): array
    {
        return ['project', 'requestedBy', 'approvedBy'];
    }

    /**
     * @return array<int, string>
     */
    private function purchaseOrderRelations(): array
    {
        return ['project', 'vendor', 'purchaseRequisition', 'createdBy', 'approvedBy', 'goodsReceipts'];
    }

    /**
     * @return array<int, string>
     */
    private function goodsReceiptRelations(): array
    {
        return ['project', 'purchaseOrder', 'receivedBy', 'stockMovements'];
    }
}
