<?php

namespace App\Application\Identity\Actions;

use App\Application\Identity\Data\UpdateProfilePhotoData;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class UpdateProfilePhoto
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(UpdateProfilePhotoData $data): User
    {
        $oldPath = $data->actor->profile_photo_path;
        $newPath = $data->photo->store('profile-photos/'.$data->actor->id, 'local');

        try {
            $data->actor->forceFill(['profile_photo_path' => $newPath])->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->auditLogger->record(
            $data->actor,
            'profile.photo.updated',
            'Updated profile photo',
            $data->actor,
            ['storage' => 'private'],
            $data->request,
        );

        return $data->actor->refresh();
    }
}
