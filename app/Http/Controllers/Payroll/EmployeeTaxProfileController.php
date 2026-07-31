<?php

namespace App\Http\Controllers\Payroll;

use App\Application\Payroll\Actions\ListEmployeeTaxProfilesForReview;
use App\Application\Payroll\Actions\LockEmployeeTaxProfile;
use App\Application\Payroll\Actions\SaveMyEmployeeTaxProfile;
use App\Application\Payroll\Actions\SubmitEmployeeTaxProfile;
use App\Application\Payroll\Actions\VerifyEmployeeTaxProfile;
use App\Application\Payroll\Actions\ViewMyEmployeeTaxProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\EmployeeTaxProfileEditRequest;
use App\Http\Requests\Payroll\EmployeeTaxProfileReviewIndexRequest;
use App\Http\Requests\Payroll\LockEmployeeTaxProfileRequest;
use App\Http\Requests\Payroll\SaveMyEmployeeTaxProfileRequest;
use App\Http\Requests\Payroll\ShowEmployeeTaxProfileRequest;
use App\Http\Requests\Payroll\SubmitEmployeeTaxProfileRequest;
use App\Http\Requests\Payroll\VerifyEmployeeTaxProfileRequest;
use App\Models\EmployeeTaxProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class EmployeeTaxProfileController extends Controller
{
    public function editMine(EmployeeTaxProfileEditRequest $request, ViewMyEmployeeTaxProfile $view): View
    {
        return view('hr.employees.tax-inputs', $view->execute($request->user(), $request->financialYear())->toView());
    }

    public function saveMine(SaveMyEmployeeTaxProfileRequest $request, SaveMyEmployeeTaxProfile $save): RedirectResponse
    {
        $profile = $save->execute($request->toData(), $request->user(), $request);

        return redirect()
            ->route('hr.employees.me.tax-inputs.edit', ['financial_year' => $profile->financial_year])
            ->with('status', $profile->supersedes_employee_tax_profile_id === null
                ? 'Tax inputs saved as a draft.'
                : 'A governed amendment draft was created without changing the locked version.');
    }

    public function submitMine(
        SubmitEmployeeTaxProfileRequest $request,
        EmployeeTaxProfile $employeeTaxProfile,
        SubmitEmployeeTaxProfile $submit,
    ): RedirectResponse {
        $profile = $submit->execute($employeeTaxProfile, $request->user(), $request->integer('lock_version'), $request);

        return redirect()
            ->route('hr.employees.me.tax-inputs.edit', ['financial_year' => $profile->financial_year])
            ->with('status', 'Tax inputs submitted for independent verification.');
    }

    public function index(EmployeeTaxProfileReviewIndexRequest $request, ListEmployeeTaxProfilesForReview $list): View
    {
        return view('payroll.employee-tax-profiles.index', $list->execute($request->user(), $request->validated())->toView());
    }

    public function show(
        ShowEmployeeTaxProfileRequest $request,
        EmployeeTaxProfile $employeeTaxProfile,
        ListEmployeeTaxProfilesForReview $list,
    ): View {
        return view('payroll.employee-tax-profiles.index', $list->execute($request->user(), [], $employeeTaxProfile)->toView());
    }

    public function verify(
        VerifyEmployeeTaxProfileRequest $request,
        EmployeeTaxProfile $employeeTaxProfile,
        VerifyEmployeeTaxProfile $verify,
    ): RedirectResponse {
        $profile = $verify->execute($employeeTaxProfile, $request->user(), $request->toData(), $request);

        return redirect()->route('payroll.employee-tax-profiles.show', $profile)->with('status', 'Tax inputs independently verified.');
    }

    public function lock(
        LockEmployeeTaxProfileRequest $request,
        EmployeeTaxProfile $employeeTaxProfile,
        LockEmployeeTaxProfile $lock,
    ): RedirectResponse {
        $profile = $lock->execute($employeeTaxProfile, $request->user(), $request->integer('lock_version'), $request);

        return redirect()->route('payroll.employee-tax-profiles.show', $profile)->with('status', 'Tax inputs locked for governed payroll use.');
    }
}
