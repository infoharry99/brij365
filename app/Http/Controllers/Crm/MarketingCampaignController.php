<?php

namespace App\Http\Controllers\Crm;

use App\Application\Crm\Actions\ChangeMarketingCampaignStatus;
use App\Application\Crm\Actions\CreateMarketingCampaign;
use App\Application\Crm\Actions\ListLeadActivityWorkspace;
use App\Application\Crm\Actions\ListMarketingCampaignWorkspace;
use App\Application\Crm\Actions\RecordLeadActivity;
use App\Application\Crm\Data\CrmCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\LeadActivityIndexRequest;
use App\Http\Requests\Crm\MarketingCampaignIndexRequest;
use App\Http\Requests\Crm\StoreLeadActivityRequest;
use App\Http\Requests\Crm\StoreMarketingCampaignRequest;
use App\Http\Requests\Crm\UpdateMarketingCampaignStatusRequest;
use App\Http\Resources\LeadActivityResource;
use App\Http\Resources\MarketingCampaignResource;
use App\Models\MarketingCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class MarketingCampaignController extends Controller
{
    public function index(MarketingCampaignIndexRequest $request, ListMarketingCampaignWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return MarketingCampaignResource::collection($page->campaigns);
        }

        return view('crm.marketing.campaigns', [
            'campaigns' => $page->campaigns->withQueryString(), 'filters' => $page->filters,
            'companies' => $page->companies, 'projects' => $page->projects, 'summary' => $page->summary,
            'statuses' => $page->statuses, 'channels' => $page->channels, 'canCreate' => $page->canCreate,
        ]);
    }

    public function store(StoreMarketingCampaignRequest $request, CreateMarketingCampaign $action): JsonResponse|RedirectResponse
    {
        $campaign = $action->execute($this->command($request));

        if ($request->wantsJson()) {
            return (new MarketingCampaignResource($campaign))->response()->setStatusCode(201);
        }

        return redirect()->route('crm.campaigns.index')->with('status', "Campaign {$campaign->campaign_code} created.");
    }

    public function updateStatus(UpdateMarketingCampaignStatusRequest $request, MarketingCampaign $marketingCampaign, ChangeMarketingCampaignStatus $action): MarketingCampaignResource|RedirectResponse
    {
        $campaign = $action->execute($marketingCampaign, $this->command($request));

        if ($request->wantsJson()) {
            return new MarketingCampaignResource($campaign);
        }

        return redirect()->route('crm.campaigns.index')->with('status', "Campaign {$campaign->campaign_code} is now {$campaign->status}.");
    }

    public function activities(LeadActivityIndexRequest $request, ListLeadActivityWorkspace $action): AnonymousResourceCollection|View
    {
        $page = $action->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return LeadActivityResource::collection($page->activities);
        }

        return view('crm.marketing.activities', [
            'activities' => $page->activities->withQueryString(), 'filters' => $page->filters,
            'projects' => $page->projects, 'campaigns' => $page->campaigns, 'leads' => $page->leads,
            'types' => $page->types, 'canCreate' => $page->canCreate,
        ]);
    }

    public function storeActivity(StoreLeadActivityRequest $request, RecordLeadActivity $action): JsonResponse|RedirectResponse
    {
        $activity = $action->execute($this->command($request));

        if ($request->wantsJson()) {
            return (new LeadActivityResource($activity))->response()->setStatusCode(201);
        }

        return redirect()->route('crm.lead-activities.index')->with('status', "Activity {$activity->activity_number} recorded.");
    }

    private function command(FormRequest $request): CrmCommandData
    {
        return new CrmCommandData($request->validated(), $request->user(), $request);
    }
}
