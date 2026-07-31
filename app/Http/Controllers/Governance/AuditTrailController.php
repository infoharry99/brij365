<?php

namespace App\Http\Controllers\Governance;

use App\Application\Governance\Actions\ExportAuditTrail;
use App\Application\Governance\Actions\ListAuditTrail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\AuditEventIndexRequest;
use App\Http\Resources\AuditEventResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AuditTrailController extends Controller
{
    public function index(AuditEventIndexRequest $request, ListAuditTrail $list): AnonymousResourceCollection|View
    {
        $page = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return AuditEventResource::collection($page->events);
        }

        return view('governance.audit-events.index', $page->toView());
    }

    public function export(AuditEventIndexRequest $request, ExportAuditTrail $export): Response
    {
        $file = $export->execute($request->user(), $request->validated(), $request);

        return response($file->csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$file->filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
