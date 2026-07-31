<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\AcknowledgeEmployeePolicy;
use App\Application\Hr\Actions\ListPolicyAcknowledgements;
use App\Application\Hr\Actions\ListPolicyAcknowledgementWorkspace;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\PolicyAcknowledgementIndexRequest;
use App\Http\Requests\Hr\StorePolicyAcknowledgementRequest;
use App\Http\Resources\EmployeePolicyAcknowledgementResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeePolicyAcknowledgementController extends Controller
{
    public function index(
        PolicyAcknowledgementIndexRequest $request,
        ListPolicyAcknowledgements $list,
        ListPolicyAcknowledgementWorkspace $workspace,
    ): AnonymousResourceCollection|View {
        if (! $request->wantsJson()) {
            return view('hr.policies.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        $data = $list->execute($request->user(), $request->validated());

        return EmployeePolicyAcknowledgementResource::collection($data->acknowledgements)->additional(['policies' => $data->policies]);
    }

    public function store(StorePolicyAcknowledgementRequest $request, AcknowledgeEmployeePolicy $acknowledge): EmployeePolicyAcknowledgementResource|RedirectResponse
    {
        $acknowledgement = $acknowledge->execute(new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new EmployeePolicyAcknowledgementResource($acknowledgement))->additional(['message' => 'Employee policy acknowledged.']) : redirect()->route('hr.policy-acknowledgements.index')->with('status', 'Policy acknowledged.');
    }
}
