<?php

namespace App\Http\Controllers\Admin;

use App\Application\Administration\Actions\ListRoleAdministrationWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleIndexRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Admin\RoleAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class RoleAdministrationController extends Controller
{
    public function index(RoleIndexRequest $request, ListRoleAdministrationWorkspace $list): AnonymousResourceCollection|RedirectResponse|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return RoleResource::collection($workspace->records);
        }

        return view('admin.roles.index', [
            'roles' => $workspace->records,
            'filters' => $workspace->filters,
            'scopeLevels' => $workspace->scopeLevels,
            'permissions' => $workspace->permissions,
            'canCreateRole' => $workspace->canCreate,
        ]);
    }

    public function store(StoreRoleRequest $request, RoleAdministrationService $service): RoleResource|RedirectResponse
    {
        $role = $service->createRole($request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('admin.roles.index')
                ->with('status', "Role {$role->name} created.");
        }

        return (new RoleResource($role))->additional(['message' => 'Role created.']);
    }

    public function update(
        Role $role,
        UpdateRoleRequest $request,
        RoleAdministrationService $service,
    ): RoleResource|RedirectResponse {
        $updatedRole = $service->updateRole($role, $request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('admin.roles.index')
                ->with('status', "Role {$updatedRole->name} updated.");
        }

        return (new RoleResource($updatedRole))->additional(['message' => 'Role updated.']);
    }

}
