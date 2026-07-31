<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Search\Actions\SearchBuilder360;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder360\GlobalSearchRequest;
use Illuminate\View\View;

final class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, SearchBuilder360 $search): View
    {
        $page = $search->handle($request->user(), $request->validated('q'));

        return view('builder360.classic.search.index', compact('page'));
    }
}
