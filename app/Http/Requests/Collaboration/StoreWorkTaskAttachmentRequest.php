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
                'max:25600', // 25 MB Max
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/bmp,image/svg+xml,image/tiff,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv,application/zip,application/x-zip-compressed,application/x-rar-compressed,application/x-7z-compressed,audio/webm,audio/ogg,audio/oga,audio/mpeg,audio/mp3,audio/mp4,audio/m4a,audio/x-m4a,audio/mp4a-latm,audio/x-mp4a,audio/aac,audio/x-aac,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/3gpp,audio/3gpp2,audio/x-3gpp,video/3gpp,video/mp4,audio/amr,audio/x-amr,audio/flac,audio/x-flac,audio/opus,audio/x-ms-wma,audio/wma,application/ogg,application/x-ogg,application/octet-stream',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'attachment.required' => 'Please select a file to upload.',
            'attachment.file' => 'The uploaded item must be a valid file.',
            'attachment.max' => 'File size limit exceeded. Only files up to 25 MB are allowed.',
            'attachment.mimetypes' => 'Allowed formats: Images (JPG, PNG, WEBP, GIF, SVG), Audio & Voice (M4A, MP3, WAV, OGG, AAC, WEBM), PDF, Office Documents, CSV, TXT, and ZIP (Max 25 MB).',
        ];
    }
}
