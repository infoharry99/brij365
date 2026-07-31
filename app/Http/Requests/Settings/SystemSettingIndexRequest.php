<?php

namespace App\Http\Requests\Settings;

use App\Models\SystemSetting;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SystemSettingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SystemSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'setting_group' => ['nullable', 'string', 'max:80'],
            'setting_key' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'active', 'archived'])],
            'scope_key' => ['nullable', 'string', 'max:64'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected(
                    $validator,
                    $this->query(),
                    ['setting_group', 'setting_key', 'status', 'scope_key', 'page'],
                );
            },
        ];
    }
}
