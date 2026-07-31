<?php

namespace App\Http\Controllers\Admin;

use App\Application\Administration\Actions\ListCompanyAdministrationWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Services\Admin\CompanyAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyAdministrationController extends Controller
{
    public function index(Request $request, ListCompanyAdministrationWorkspace $list): View
    {
        abort_unless($request->user()?->can('create', \App\Models\Company::class), 403);

        $workspace = $list->execute();

        return view('admin.companies.index', ['companies' => $workspace->records]);
    }

    public function store(StoreCompanyRequest $request, CompanyAdministrationService $service): CompanyResource|RedirectResponse
    {
        $company = $service->createCompany($request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('admin.companies.index')
                ->with('status', "Company {$company->code} created.");
        }

        return (new CompanyResource($company))->additional(['message' => 'Company created.']);
    }
}
