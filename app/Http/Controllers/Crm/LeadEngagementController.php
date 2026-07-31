<?php

namespace App\Http\Controllers\Crm;

use App\Application\Crm\Actions\CancelSiteVisit;
use App\Application\Crm\Actions\CompleteSiteVisit;
use App\Application\Crm\Actions\ListLeadQualificationWorkspace;
use App\Application\Crm\Actions\ListSiteVisitWorkspace;
use App\Application\Crm\Actions\RecordLeadQualification;
use App\Application\Crm\Actions\ScheduleSiteVisit;
use App\Application\Crm\Actions\UpdateSiteVisit;
use App\Application\Crm\Data\CrmCommandData;
use App\Application\Crm\Data\LeadQualificationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CancelSiteVisitRequest;
use App\Http\Requests\Crm\CompleteSiteVisitRequest;
use App\Http\Requests\Crm\LeadQualificationIndexRequest;
use App\Http\Requests\Crm\SiteVisitIndexRequest;
use App\Http\Requests\Crm\StoreLeadQualificationRequest;
use App\Http\Requests\Crm\StoreSiteVisitRequest;
use App\Http\Requests\Crm\UpdateSiteVisitRequest;
use App\Http\Resources\LeadQualificationResource;
use App\Http\Resources\SiteVisitResource;
use App\Models\SiteVisit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class LeadEngagementController extends Controller
{
    public function qualifications(
        LeadQualificationIndexRequest $request,
        ListLeadQualificationWorkspace $action,
    ): AnonymousResourceCollection|View {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return LeadQualificationResource::collection($page->qualifications);
        }

        return view('crm.lead-qualifications.index', [
            'filters' => $page->filters,
            'qualifications' => $page->qualifications->withQueryString(),
            'leads' => $page->leads,
            'rules' => $page->rules,
            'statuses' => $page->statuses,
            'canQualify' => $page->canQualify,
            'canManageScoring' => $page->canManageScoring,
            'scoringUrl' => $page->scoringUrl,
            'leadScores' => $page->leadScores,
        ]);
    }

    public function storeQualification(StoreLeadQualificationRequest $request, RecordLeadQualification $action): JsonResponse|RedirectResponse
    {
        $qualification = $action->execute(LeadQualificationData::from($request->validated()), $request->user(), $request);

        if ($request->wantsJson()) {
            return (new LeadQualificationResource($qualification))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route('crm.lead-qualifications.index')
            ->with('status', "Lead qualification {$qualification->qualification_number} recorded with score {$qualification->score}.");
    }

    public function siteVisits(SiteVisitIndexRequest $request, ListSiteVisitWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return SiteVisitResource::collection($page->visits);
        }

        return view('crm.site-visits.index', [
            'filters' => $page->filters,
            'visits' => $page->visits->withQueryString(),
            'leads' => $page->leads,
            'assignees' => $page->assignees,
            'visitModes' => $page->visitModes,
            'statuses' => $page->statuses,
            'outcomes' => $page->outcomes,
            'canSchedule' => $page->canSchedule,
        ]);
    }

    public function storeSiteVisit(StoreSiteVisitRequest $request, ScheduleSiteVisit $action): JsonResponse|RedirectResponse
    {
        $visit = $action->execute($this->command($request));

        if ($request->wantsJson()) {
            return (new SiteVisitResource($visit))
                ->response()
                ->setStatusCode(201);
        }

        return redirect()
            ->route('crm.site-visits.index')
            ->with('status', "Site visit {$visit->visit_number} scheduled.");
    }

    public function updateSiteVisit(UpdateSiteVisitRequest $request, SiteVisit $siteVisit, UpdateSiteVisit $action): SiteVisitResource|RedirectResponse
    {
        $visit = $action->execute($siteVisit, $this->command($request));

        if ($request->wantsJson()) {
            return new SiteVisitResource($visit);
        }

        return redirect()
            ->route('crm.site-visits.index')
            ->with('status', "Site visit {$visit->visit_number} updated.");
    }

    public function completeSiteVisit(CompleteSiteVisitRequest $request, SiteVisit $siteVisit, CompleteSiteVisit $action): SiteVisitResource|RedirectResponse
    {
        $visit = $action->execute($siteVisit, $this->command($request));

        if ($request->wantsJson()) {
            return new SiteVisitResource($visit);
        }

        return redirect()
            ->route('crm.site-visits.index')
            ->with('status', "Site visit {$visit->visit_number} completed.");
    }

    public function cancelSiteVisit(CancelSiteVisitRequest $request, SiteVisit $siteVisit, CancelSiteVisit $action): SiteVisitResource|RedirectResponse
    {
        $visit = $action->execute($siteVisit, $this->command($request));

        if ($request->wantsJson()) {
            return new SiteVisitResource($visit);
        }

        return redirect()
            ->route('crm.site-visits.index')
            ->with('status', "Site visit {$visit->visit_number} cancelled.");
    }

    private function command(FormRequest $request): CrmCommandData
    {
        return new CrmCommandData($request->validated(), $request->user(), $request);
    }
}
