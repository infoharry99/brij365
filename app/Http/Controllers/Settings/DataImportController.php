<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\Actions\ListDataImportWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DataImportBatchIndexRequest;
use App\Http\Requests\Settings\PostDataImportBatchRequest;
use App\Http\Requests\Settings\PreviewDataImportBatchRequest;
use App\Http\Resources\DataImportBatchResource;
use App\Models\DataImportBatch;
use App\Services\Settings\DataImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class DataImportController extends Controller
{
    public function index(DataImportBatchIndexRequest $request, ListDataImportWorkspace $list): AnonymousResourceCollection|RedirectResponse|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return DataImportBatchResource::collection($workspace->records);
        }

        return view('settings.data-imports.index', [
            'batches' => $workspace->records,
            'filters' => $workspace->filters,
            'companies' => $workspace->companies,
            'importTypes' => $workspace->types,
            'statuses' => $workspace->statuses,
            'canCreateImport' => $workspace->canCreate,
        ]);
    }

    public function preview(PreviewDataImportBatchRequest $request, DataImportService $service): DataImportBatchResource|RedirectResponse
    {
        $batch = $service->preview(
            $request->validated(),
            $request->file('source_file'),
            $request->user(),
            $request,
        );

        if (! $request->wantsJson()) {
            return redirect()
                ->route('settings.data-imports.index')
                ->with('status', "Import {$batch->import_number} preview generated: {$batch->valid_rows} valid row(s), {$batch->invalid_rows} invalid row(s).");
        }

        return (new DataImportBatchResource($batch))->additional(['message' => 'Data import preview generated.']);
    }

    public function post(
        DataImportBatch $dataImportBatch,
        PostDataImportBatchRequest $request,
        DataImportService $service,
    ): DataImportBatchResource|RedirectResponse {
        $batch = $service->post($dataImportBatch, $request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('settings.data-imports.index')
                ->with('status', "Import {$batch->import_number} posted to business records.");
        }

        return (new DataImportBatchResource($batch))->additional(['message' => 'Data import posted to business records.']);
    }

}
