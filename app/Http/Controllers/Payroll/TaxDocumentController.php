<?php

namespace App\Http\Controllers\Payroll;

use App\Application\Payroll\Actions\AcknowledgeTaxDocument;
use App\Application\Payroll\Actions\GenerateTaxDocument;
use App\Application\Payroll\Actions\IssueTaxDocument;
use App\Application\Payroll\Actions\ListTaxDocuments;
use App\Application\Payroll\Data\PayrollCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\AcknowledgeTaxDocumentRequest;
use App\Http\Requests\Payroll\GenerateTaxDocumentRequest;
use App\Http\Requests\Payroll\IssueTaxDocumentRequest;
use App\Http\Requests\Payroll\TaxDocumentIndexRequest;
use App\Http\Resources\EmployeeTaxDocumentResource;
use App\Models\EmployeeTaxDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxDocumentController extends Controller
{
    public function index(TaxDocumentIndexRequest $request, ListTaxDocuments $list): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $documents = $list->execute($request->user(), $filters);

        return EmployeeTaxDocumentResource::collection($documents);
    }

    public function store(GenerateTaxDocumentRequest $request, GenerateTaxDocument $generate): JsonResponse
    {
        $document = $generate->execute(new PayrollCommandData($request->validated(), $request->user(), $request));

        return (new EmployeeTaxDocumentResource($document))
            ->additional(['message' => 'Employee tax document generated.'])
            ->response()
            ->setStatusCode(201);
    }

    public function issue(EmployeeTaxDocument $employeeTaxDocument, IssueTaxDocumentRequest $request, IssueTaxDocument $issue): EmployeeTaxDocumentResource
    {
        return (new EmployeeTaxDocumentResource($issue->execute($employeeTaxDocument, new PayrollCommandData($request->validated(), $request->user(), $request))))
            ->additional(['message' => 'Employee tax document issued.']);
    }

    public function acknowledge(EmployeeTaxDocument $employeeTaxDocument, AcknowledgeTaxDocumentRequest $request, AcknowledgeTaxDocument $acknowledge): EmployeeTaxDocumentResource
    {
        return (new EmployeeTaxDocumentResource($acknowledge->execute($employeeTaxDocument, new PayrollCommandData($request->validated(), $request->user(), $request))))
            ->additional(['message' => 'Employee tax document acknowledged.']);
    }
}
