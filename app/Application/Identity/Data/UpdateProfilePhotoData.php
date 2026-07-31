<?php

namespace App\Application\Identity\Data;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final readonly class UpdateProfilePhotoData
{
    public function __construct(
        public User $actor,
        public UploadedFile $photo,
        public Request $request,
    ) {}
}
