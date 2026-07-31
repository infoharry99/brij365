<?php

namespace App\Http\Controllers\Settings;

use App\Application\Settings\Actions\ListSystemSettingWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApproveSystemSettingRequest;
use App\Http\Requests\Settings\StoreSystemSettingRequest;
use App\Http\Requests\Settings\SystemSettingIndexRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Services\Settings\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(SystemSettingIndexRequest $request, ListSystemSettingWorkspace $list): AnonymousResourceCollection|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if (! $request->wantsJson()) {
            return view('settings.system-settings.index', [
                'settings' => $workspace->records,
                'filters' => $workspace->filters,
                'companies' => $workspace->companies,
                'groups' => $workspace->groups,
                'keys' => $workspace->keys,
                'statuses' => $workspace->statuses,
                'valueTypes' => $workspace->types,
                'canCreateSetting' => $workspace->canCreate,
            ]);
        }

        return SystemSettingResource::collection($workspace->records);
    }

    public function store(StoreSystemSettingRequest $request, SystemSettingService $service): SystemSettingResource|RedirectResponse
    {
        $setting = $service->createDraft($request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('settings.system-settings.index')
                ->with('status', "System setting {$setting->setting_key} draft v{$setting->version} created.");
        }

        return (new SystemSettingResource($setting))->additional(['message' => 'System setting draft created.']);
    }

    public function approve(
        SystemSetting $systemSetting,
        ApproveSystemSettingRequest $request,
        SystemSettingService $service,
    ): SystemSettingResource|RedirectResponse {
        $setting = $service->approve($systemSetting, $request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('settings.system-settings.index')
                ->with('status', "System setting {$setting->setting_key} v{$setting->version} approved and activated.");
        }

        return (new SystemSettingResource($setting))->additional(['message' => 'System setting approved and activated.']);
    }

}
