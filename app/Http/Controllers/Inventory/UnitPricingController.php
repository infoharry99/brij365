<?php

namespace App\Http\Controllers\Inventory;

use App\Application\Inventory\Actions\ApproveUnitPriceVersion;
use App\Application\Inventory\Actions\CreateUnitPriceVersion;
use App\Application\Inventory\Actions\ListUnitPricingWorkspace;
use App\Application\Inventory\Data\InventoryCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ApproveUnitPriceVersionRequest;
use App\Http\Requests\Inventory\StoreUnitPriceVersionRequest;
use App\Http\Requests\Inventory\UnitPriceVersionIndexRequest;
use App\Http\Resources\UnitPriceVersionResource;
use App\Models\UnitPriceVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class UnitPricingController extends Controller
{
    public function index(UnitPriceVersionIndexRequest $request, ListUnitPricingWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return UnitPriceVersionResource::collection($page->versions);
        }

        return view('inventory.unit-price-versions.index', [
            'versions' => $page->versions->withQueryString(), 'filters' => $page->filters,
            'projects' => $page->projects, 'units' => $page->units, 'statuses' => $page->statuses,
            'canCreateVersion' => $page->canCreate,
        ]);
    }

    public function store(StoreUnitPriceVersionRequest $request, CreateUnitPriceVersion $action): JsonResponse|RedirectResponse
    {
        $version = $action->execute(new InventoryCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('inventory.unit-price-versions.index')
                ->with('status', "Unit price version {$version->price_code} drafted for approval.");
        }

        return (new UnitPriceVersionResource($version))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(ApproveUnitPriceVersionRequest $request, UnitPriceVersion $unitPriceVersion, ApproveUnitPriceVersion $action): UnitPriceVersionResource|RedirectResponse
    {
        $version = $action->execute($unitPriceVersion, new InventoryCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()
                ->route('inventory.unit-price-versions.index')
                ->with('status', "Unit price version {$version->price_code} approved.");
        }

        return new UnitPriceVersionResource($version);
    }
}
