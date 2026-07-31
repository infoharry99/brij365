<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveComplianceRule;
use App\Application\Hr\Actions\CreateComplianceRuleDraft;
use App\Application\Hr\Actions\ListComplianceRules;
use App\Application\Hr\Actions\ListComplianceRuleWorkspace;
use App\Application\Hr\Actions\VerifyStatutoryRulePack;
use App\Application\Payroll\Actions\SimulateStatutoryRulePack;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveComplianceRuleSettingRequest;
use App\Http\Requests\Hr\ComplianceRuleSettingIndexRequest;
use App\Http\Requests\Hr\StoreComplianceRuleSettingRequest;
use App\Http\Requests\Hr\SimulateStatutoryRulePackRequest;
use App\Http\Requests\Hr\VerifyStatutoryRulePackRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ComplianceRuleSettingController extends Controller
{
    public function index(ComplianceRuleSettingIndexRequest $request, ListComplianceRules $list, ListComplianceRuleWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.compliance.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return SystemSettingResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function store(StoreComplianceRuleSettingRequest $request, CreateComplianceRuleDraft $create): SystemSettingResource|RedirectResponse
    {
        $setting = $create->execute(new HrCommandData($request->normalizedPayload(), $request->user(), $request));

        if ($request->wantsJson()) {
            return (new SystemSettingResource($setting))->additional(['message' => 'Compliance rule setting draft created.']);
        }

        return $request->input('return_to') === 'logic_center'
            ? redirect()->route('scoring.index', ['view' => 'statutory'])->with('status', 'Governed statutory pack draft created. It cannot affect payroll until independently verified and approved.')
            : redirect()->route('hr.compliance-rule-settings.index')->with('status', 'Compliance rule draft created.');
    }

    public function approve(
        SystemSetting $systemSetting,
        ApproveComplianceRuleSettingRequest $request,
        ApproveComplianceRule $approve,
    ): SystemSettingResource|RedirectResponse {
        $setting = $approve->execute($systemSetting, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson() ? (new SystemSettingResource($setting))->additional(['message' => 'Compliance rule setting approved and activated.']) : redirect()->route('hr.compliance-rule-settings.index')->with('status', 'Compliance rule approved and activated.');
    }

    public function verify(
        SystemSetting $systemSetting,
        VerifyStatutoryRulePackRequest $request,
        VerifyStatutoryRulePack $verify,
    ): SystemSettingResource|RedirectResponse {
        $setting = $verify->execute($systemSetting, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? (new SystemSettingResource($setting))->additional(['message' => 'Statutory source evidence verified.'])
            : redirect()->route('hr.compliance-rule-settings.index')->with('status', 'Statutory source evidence verified. A different authorized user must approve the version.');
    }

    public function simulate(
        SystemSetting $systemSetting,
        SimulateStatutoryRulePackRequest $request,
        SimulateStatutoryRulePack $simulate,
    ): JsonResponse|RedirectResponse {
        $result = $simulate->execute($systemSetting, $request->simulationPayload());

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $result->toArray(),
                'message' => 'Simulation completed without mutating payroll or statutory records.',
            ]);
        }

        return redirect()->route('scoring.index', ['view' => 'simulation'])
            ->with('status', 'Non-authoritative simulation completed. No payroll, attendance, or statutory record was changed.')
            ->with('statutory_simulation', [
                'setting_id' => (int) $systemSetting->id,
                'setting_label' => $systemSetting->label,
                'setting_version' => (int) $systemSetting->version,
                'setting_status' => $systemSetting->status,
                'result' => $result->toView(),
            ]);
    }
}
