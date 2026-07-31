<?php

namespace App\Http\Controllers\Crm;

use App\Application\Crm\Actions\AssignProspectInquiry;
use App\Application\Crm\Actions\CaptureProspectInquiry;
use App\Application\Crm\Actions\CloseProspectInquiry;
use App\Application\Crm\Actions\ConvertProspectInquiry;
use App\Application\Crm\Actions\ListProspectInquiryWorkspace;
use App\Application\Crm\Data\CrmCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\AssignProspectInquiryRequest;
use App\Http\Requests\Crm\CloseProspectInquiryRequest;
use App\Http\Requests\Crm\ConvertProspectInquiryRequest;
use App\Http\Requests\Crm\ProspectInquiryIndexRequest;
use App\Http\Requests\Crm\PublicStoreProspectInquiryRequest;
use App\Http\Resources\ProspectInquiryResource;
use App\Models\ProspectInquiry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class ProspectInquiryController extends Controller
{
    public function index(ProspectInquiryIndexRequest $request, ListProspectInquiryWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return ProspectInquiryResource::collection($page->inquiries);
        }

        return view('crm.prospect-inquiries.index', [
            'filters' => $page->filters, 'inquiries' => $page->inquiries->withQueryString(),
            'projects' => $page->projects, 'campaigns' => $page->campaigns, 'assignees' => $page->assignees,
            'sources' => $page->sources, 'channels' => $page->channels, 'statuses' => $page->statuses,
            'metrics' => $page->metrics, 'canManage' => $page->canManage,
        ]);
    }

    public function storePublic(PublicStoreProspectInquiryRequest $request, CaptureProspectInquiry $action): JsonResponse
    {
        $inquiry = $action->execute($request->validated(), $request);

        return (new ProspectInquiryResource($inquiry))
            ->response()
            ->setStatusCode(201);
    }

    public function assign(
        AssignProspectInquiryRequest $request,
        ProspectInquiry $prospectInquiry,
        AssignProspectInquiry $action,
    ): ProspectInquiryResource|RedirectResponse {
        $inquiry = $action->execute($prospectInquiry, $this->command($request));

        if ($request->wantsJson()) {
            return new ProspectInquiryResource($inquiry);
        }

        return redirect()
            ->route('crm.prospect-inquiries.index')
            ->with('status', "Prospect inquiry {$inquiry->inquiry_number} assigned to {$inquiry->assignedTo?->name}.");
    }

    public function convert(
        ConvertProspectInquiryRequest $request,
        ProspectInquiry $prospectInquiry,
        ConvertProspectInquiry $action,
    ): ProspectInquiryResource|RedirectResponse {
        $inquiry = $action->execute($prospectInquiry, $this->command($request));

        if ($request->wantsJson()) {
            return new ProspectInquiryResource($inquiry);
        }

        return redirect()
            ->route('crm.prospect-inquiries.index', ['status' => ProspectInquiry::STATUS_CONVERTED])
            ->with('status', "Prospect inquiry {$inquiry->inquiry_number} converted to lead {$inquiry->convertedLead?->lead_code}.");
    }

    public function close(
        CloseProspectInquiryRequest $request,
        ProspectInquiry $prospectInquiry,
        CloseProspectInquiry $action,
    ): ProspectInquiryResource|RedirectResponse {
        $inquiry = $action->execute($prospectInquiry, $this->command($request));

        if ($request->wantsJson()) {
            return new ProspectInquiryResource($inquiry);
        }

        return redirect()
            ->route('crm.prospect-inquiries.index', ['status' => $inquiry->status])
            ->with('status', "Prospect inquiry {$inquiry->inquiry_number} closed.");
    }

    private function command(FormRequest $request): CrmCommandData
    {
        return new CrmCommandData($request->validated(), $request->user(), $request);
    }
}
