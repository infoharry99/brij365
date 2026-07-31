<?php

namespace App\Http\Controllers\Builder360;

use App\Application\Identity\Actions\UpdateProfilePhoto;
use App\Application\Identity\Data\UpdateProfilePhotoData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Builder360\UpdateProfilePhotoRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfilePhotoController extends Controller
{
    public function update(UpdateProfilePhotoRequest $request, UpdateProfilePhoto $update): RedirectResponse
    {
        $update->handle(new UpdateProfilePhotoData(
            actor: $request->user(),
            photo: $request->file('photo'),
            request: $request,
        ));

        return redirect()->route('builder360.profile')->with('status', 'Profile photo updated.');
    }

    public function show(Request $request, User $user): StreamedResponse
    {
        $this->authorize('viewProfilePhoto', $user);

        abort_unless($user->profile_photo_path && Storage::disk('local')->exists($user->profile_photo_path), 404);

        return Storage::disk('local')->response($user->profile_photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
