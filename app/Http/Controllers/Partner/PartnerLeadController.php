<?php

namespace App\Http\Controllers\Partner;

use App\Application\Partner\Actions\ListPartnerLeads;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\PartnerLeadIndexRequest;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class PartnerLeadController extends Controller
{
    public function index(PartnerLeadIndexRequest $request, ListPartnerLeads $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        return $request->wantsJson()
            ? LeadResource::collection($workspace->records)
            : view('partner.leads', $workspace->toView());
    }
}
