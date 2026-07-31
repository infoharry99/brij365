<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ApproveEmployeeDocument;
use App\Application\Hr\Actions\ListEmployeeDocumentRegister;
use App\Application\Hr\Actions\ListEmployeeDocuments;
use App\Application\Hr\Actions\ListEmployeeDocumentWorkspace;
use App\Application\Hr\Actions\SubmitEmployeeDocument;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApproveEmployeeDocumentRequest;
use App\Http\Requests\Hr\EmployeeDocumentIndexRequest;
use App\Http\Requests\Hr\EmployeeDocumentRegisterIndexRequest;
use App\Http\Requests\Hr\StoreEmployeeDocumentRequest;
use App\Http\Resources\ManagedDocumentResource;
use App\Models\Employee;
use App\Models\ManagedDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class EmployeeDocumentController extends Controller
{
    public function register(EmployeeDocumentRegisterIndexRequest $request, ListEmployeeDocumentRegister $list, ListEmployeeDocumentWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.documents.index', $workspace->execute($request->user(), $request->validated())->toView());
        }

        return ManagedDocumentResource::collection($list->execute($request->user(), $request->validated()));
    }

    public function index(EmployeeDocumentIndexRequest $request, Employee $employee, ListEmployeeDocuments $list, ListEmployeeDocumentWorkspace $workspace): AnonymousResourceCollection|View
    {
        if (! $request->wantsJson()) {
            return view('hr.documents.index', $workspace->execute($request->user(), $request->validated(), $employee)->toView());
        }

        return ManagedDocumentResource::collection($list->execute($employee, $request->validated()));
    }

    public function store(
        StoreEmployeeDocumentRequest $request,
        Employee $employee,
        SubmitEmployeeDocument $submit,
    ): JsonResponse|RedirectResponse {
        $document = $submit->execute(new HrCommandData($request->validated(), $request->user(), $request));

        if (! $request->wantsJson()) {
            return redirect()->route('hr.employees.documents.index', $employee)->with('status', 'Employee document '.$document->document_number.' submitted.');
        }

        return (new ManagedDocumentResource($document))
            ->response()
            ->setStatusCode(201);
    }

    public function approve(
        ApproveEmployeeDocumentRequest $request,
        Employee $employee,
        ManagedDocument $managedDocument,
        ApproveEmployeeDocument $approve,
    ): ManagedDocumentResource|RedirectResponse {
        $document = $approve->execute($managedDocument, new HrCommandData($request->validated(), $request->user(), $request));

        return $request->wantsJson()
            ? new ManagedDocumentResource($document)
            : redirect()->route('hr.employees.documents.index', $employee)->with('status', 'Employee document approved.');
    }
}
