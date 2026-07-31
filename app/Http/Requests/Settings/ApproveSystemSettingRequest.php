<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;

class ApproveSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $systemSetting = $this->route('systemSetting');

        return $systemSetting instanceof SystemSetting
            && $this->user()?->can('approve', $systemSetting) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
