<?php

namespace App\Http\Controllers\Hr;

use App\Application\Hr\Actions\ListHrSettingsWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HrSettingIndexRequest;
use Illuminate\View\View;

final class HrSettingController extends Controller
{
    public function __invoke(HrSettingIndexRequest $request, ListHrSettingsWorkspace $list): View
    {
        return view('hr.settings.index', [
            'workspace' => $list->execute($request->user(), $request->validated())->toView(),
        ]);
    }
}
