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
                'mimes:jpg,jpeg,png,gif,webp,bmp,svg,tiff,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,mp3,wav,m4a,aac,ogg,oga,webm,3gp,3gpp,amr,flac,opus,wma,mp4,mov,avi,mkv,3g2',
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
