<?php

namespace App\Http\Requests\Collaboration;

use App\Models\WorkTask;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('workTask');

        return $task instanceof WorkTask && ($this->user()?->can('updateDetails', $task) ?? false);
    }

    public function rules(): array
    {
        return [
            'attachment' => [
                'required',
                'file',
                'max:5120', // 5 MB Max
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/bmp,image/svg+xml,image/tiff,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/zip,application/x-zip-compressed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Please select a file to upload.',
            'attachment.file' => 'The uploaded item must be a valid file.',
            'attachment.max' => 'File size limit exceeded. Only files up to 5 MB are allowed.',
            'attachment.mimetypes' => 'Video files are not allowed. Allowed formats: Images (JPG, PNG, WEBP, GIF, SVG), PDF, Office Documents, CSV, TXT, and ZIP (Max 5 MB).',
        ];
    }
}
