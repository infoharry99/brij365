<?php

namespace App\Http\Controllers\Documents;

use App\Application\Documents\Actions\ListDocumentCategoryWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\DocumentCategoryIndexRequest;
use App\Http\Resources\DocumentCategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class DocumentCategoryController extends Controller
{
    public function index(
        DocumentCategoryIndexRequest $request,
        ListDocumentCategoryWorkspace $list,
    ): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('documents.categories.index', $workspace->toView());
        }

        return DocumentCategoryResource::collection($workspace->categories);
    }
}
