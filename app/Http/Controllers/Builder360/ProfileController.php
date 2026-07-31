<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Identity\Actions\ShowProfile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __invoke(Request $request, ShowProfile $showProfile): View
    {
        $user = $request->user();
        $this->authorize('viewOwnProfile', $user);
        $page = $showProfile->handle($user);

        return view('builder360.classic.profile', [
            'page' => $page,
        ]);
    }
}
