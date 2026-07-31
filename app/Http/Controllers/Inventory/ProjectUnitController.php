<?php

namespace App\Http\Controllers\Inventory;

use App\Application\Inventory\Actions\ExportUnitAvailability;
use App\Application\Inventory\Actions\ListUnitInventoryWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProjectUnitIndexRequest;
use App\Http\Resources\ProjectUnitResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProjectUnitController extends Controller
{
    public function index(ProjectUnitIndexRequest $request, ListUnitInventoryWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return ProjectUnitResource::collection($page->units);
        }

        return view('inventory.units.index', [
            'units' => $page->units->withQueryString(), 'filters' => $page->filters,
            'projects' => $page->projects, 'unitTypes' => $page->unitTypes,
            'statuses' => $page->statuses, 'summary' => $page->summary,
        ]);
    }

    public function export(
        ProjectUnitIndexRequest $request,
        ExportUnitAvailability $action,
    ): Response {
        $filters = $request->validated();
        unset($filters['page'], $filters['per_page'], $filters['format']);
        $export = $action->execute($request->user(), $filters, $request);

        return response($export->content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export->filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
