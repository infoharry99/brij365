<?php

namespace App\Http\Controllers\Legal;

use App\Application\Legal\Actions\CompleteComplianceObligation;
use App\Application\Legal\Actions\CreateComplianceObligation;
use App\Application\Legal\Actions\CreateProjectApproval;
use App\Application\Legal\Actions\CreateReraRegistration;
use App\Application\Legal\Actions\ListComplianceObligationWorkspace;
use App\Application\Legal\Actions\ListProjectApprovalWorkspace;
use App\Application\Legal\Actions\ListReraRegistrationWorkspace;
use App\Application\Legal\Actions\VerifyProjectApproval;
use App\Application\Legal\Actions\VerifyReraRegistration;
use App\Application\Legal\Data\LegalCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Legal\CompleteComplianceObligationRequest;
use App\Http\Requests\Legal\ComplianceObligationIndexRequest;
use App\Http\Requests\Legal\ProjectApprovalIndexRequest;
use App\Http\Requests\Legal\ReraRegistrationIndexRequest;
use App\Http\Requests\Legal\StoreComplianceObligationRequest;
use App\Http\Requests\Legal\StoreProjectApprovalRequest;
use App\Http\Requests\Legal\StoreReraRegistrationRequest;
use App\Http\Requests\Legal\VerifyProjectApprovalRequest;
use App\Http\Requests\Legal\VerifyReraRegistrationRequest;
use App\Http\Resources\ComplianceObligationResource;
use App\Http\Resources\ProjectApprovalResource;
use App\Http\Resources\ReraRegistrationResource;
use App\Models\ComplianceObligation;
use App\Models\ProjectApproval;
use App\Models\ReraRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class LegalComplianceController extends Controller
{
    public function reraRegistrations(ReraRegistrationIndexRequest $request, ListReraRegistrationWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());
        return $request->wantsJson() ? ReraRegistrationResource::collection($workspace->registrations) : view('legal.rera-registrations.index', $workspace->toView());
    }

    public function storeReraRegistration(StoreReraRegistrationRequest $request, CreateReraRegistration $create): ReraRegistrationResource|RedirectResponse
    {
        $registration = $create->execute(new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ReraRegistrationResource($registration))->additional(['message' => 'RERA registration submitted.']) : redirect()->route('legal.rera-registrations.index')->with('status', "RERA registration {$registration->registration_number} submitted.");
    }

    public function verifyReraRegistration(ReraRegistration $reraRegistration, VerifyReraRegistrationRequest $request, VerifyReraRegistration $verify): ReraRegistrationResource|RedirectResponse
    {
        $registration = $verify->execute($reraRegistration, new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ReraRegistrationResource($registration))->additional(['message' => 'RERA registration verified.']) : redirect()->route('legal.rera-registrations.index')->with('status', "RERA registration {$registration->registration_number} verified.");
    }

    public function projectApprovals(ProjectApprovalIndexRequest $request, ListProjectApprovalWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());
        return $request->wantsJson() ? ProjectApprovalResource::collection($workspace->approvals) : view('legal.project-approvals.index', $workspace->toView());
    }

    public function storeProjectApproval(StoreProjectApprovalRequest $request, CreateProjectApproval $create): ProjectApprovalResource|RedirectResponse
    {
        $approval = $create->execute(new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ProjectApprovalResource($approval))->additional(['message' => 'Project approval recorded.']) : redirect()->route('legal.project-approvals.index')->with('status', "Project approval {$approval->approval_code} recorded.");
    }

    public function verifyProjectApproval(ProjectApproval $projectApproval, VerifyProjectApprovalRequest $request, VerifyProjectApproval $verify): ProjectApprovalResource|RedirectResponse
    {
        $approval = $verify->execute($projectApproval, new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ProjectApprovalResource($approval))->additional(['message' => 'Project approval verified.']) : redirect()->route('legal.project-approvals.index')->with('status', "Project approval {$approval->approval_code} verified.");
    }

    public function complianceObligations(ComplianceObligationIndexRequest $request, ListComplianceObligationWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());
        return $request->wantsJson() ? ComplianceObligationResource::collection($workspace->obligations) : view('legal.compliance-obligations.index', $workspace->toView());
    }

    public function storeComplianceObligation(StoreComplianceObligationRequest $request, CreateComplianceObligation $create): ComplianceObligationResource|RedirectResponse
    {
        $obligation = $create->execute(new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ComplianceObligationResource($obligation))->additional(['message' => 'Compliance obligation created.']) : redirect()->route('legal.compliance-obligations.index')->with('status', "Compliance obligation {$obligation->obligation_number} created.");
    }

    public function completeComplianceObligation(ComplianceObligation $complianceObligation, CompleteComplianceObligationRequest $request, CompleteComplianceObligation $complete): ComplianceObligationResource|RedirectResponse
    {
        $obligation = $complete->execute($complianceObligation, new LegalCommandData($request->validated(), $request->user(), $request));
        return $request->wantsJson() ? (new ComplianceObligationResource($obligation))->additional(['message' => 'Compliance obligation completed.']) : redirect()->route('legal.compliance-obligations.index')->with('status', "Compliance obligation {$obligation->obligation_number} completed.");
    }
}
