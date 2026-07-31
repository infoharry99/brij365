<?php

namespace App\Http\Controllers\Crm;

use App\Application\Crm\Actions\ChangeLeadStage;
use App\Application\Crm\Actions\CreateLead;
use App\Application\Crm\Actions\DisposeLead;
use App\Application\Crm\Actions\ListLeadWorkspace;
use App\Application\Crm\Data\CrmCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DisposeLeadRequest;
use App\Http\Requests\Crm\LeadIndexRequest;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadStageRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(LeadIndexRequest $request, ListLeadWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return LeadResource::collection($page->leads);
        }

        return view('crm.leads.index', [
            'filters' => $page->filters,
            'leads' => $page->leads->withQueryString(),
            'companies' => $page->companies,
            'projects' => $page->projects,
            'campaigns' => $page->campaigns,
            'partners' => $page->partners,
            'sources' => $page->sources,
            'stages' => $page->stages,
            'statuses' => $page->statuses,
            'canCreate' => $page->canCreate,
        ]);
    }

    public function store(StoreLeadRequest $request, CreateLead $action): JsonResponse|RedirectResponse
    {
        $lead = $action->execute($this->command($request));

        if ($request->wantsJson()) {
            return (new LeadResource($lead))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route('crm.leads.index')
            ->with('status', "Lead {$lead->lead_code} created for {$lead->customer?->name}.");
    }

    public function updateStage(UpdateLeadStageRequest $request, Lead $lead, ChangeLeadStage $action): LeadResource
    {
        return new LeadResource($action->execute($lead, $this->command($request)));
    }

    public function dispose(DisposeLeadRequest $request, Lead $lead, DisposeLead $action): LeadResource
    {
        return new LeadResource($action->execute($lead, $this->command($request)));
    }

    private function command(FormRequest $request): CrmCommandData
    {
        return new CrmCommandData($request->validated(), $request->user(), $request);
    }
}
