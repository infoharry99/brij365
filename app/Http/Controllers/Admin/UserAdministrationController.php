<?php

namespace App\Http\Controllers\Admin;

use App\Application\Administration\Actions\ListUserAdministrationWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserAccessRequest;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Resources\UserAdminResource;
use App\Models\User;
use App\Services\Admin\UserAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\View\View;

class UserAdministrationController extends Controller
{
    public function index(UserIndexRequest $request, ListUserAdministrationWorkspace $list): AnonymousResourceCollection|RedirectResponse|View
    {
        $workspace = $list->execute($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return UserAdminResource::collection($workspace->records);
        }

        return view('admin.users.index', [
            'users' => $workspace->records,
            'filters' => $workspace->filters,
            'companies' => $workspace->companies,
            'roles' => $workspace->roles,
            'statuses' => $workspace->statuses,
            'canCreateUser' => $workspace->canCreate,
        ]);
    }

    public function store(StoreUserRequest $request, UserAdministrationService $service): UserAdminResource|RedirectResponse
    {
        $user = $service->createUser($request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', "User {$user->email} created.");
        }

        return (new UserAdminResource($user))->additional(['message' => 'User account created.']);
    }

    public function updateAccess(
        User $user,
        UpdateUserAccessRequest $request,
        UserAdministrationService $service,
    ): UserAdminResource|RedirectResponse {
        $managedUser = $service->updateAccess($user, $request->validated(), $request->user(), $request);

        if (! $request->wantsJson()) {
            return redirect()
                ->route('admin.users.index')
                ->with('status', "User {$managedUser->email} access updated.");
        }

        return (new UserAdminResource($managedUser))->additional(['message' => 'User access updated.']);
    }

}
