<?php

namespace App\Http\Requests\Builder360;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateProfilePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewOwnProfile', $this->user()) ?? false;
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
        ];
    }
}
