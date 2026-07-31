<?php

namespace App\Http\Requests\Hr;

use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\SystemSetting;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ApproveComplianceRuleSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('systemSetting');
        $user = $this->user();

        return $setting instanceof SystemSetting
            && in_array($setting->setting_key, ComplianceRuleSettingIndexRequest::ALLOWED_SETTING_KEYS, true)
            && ($user?->hasPermission('compliance.manage') === true
                || $user?->hasPermission('settings.approve') === true
                || $user?->hasPermission(LogicCenterPermissions::STATUTORY_APPROVE) === true)
            && $user !== null
            && app(CompanyScopeService::class)->allowsSettingMutation($user, $setting->company_id);
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

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $setting = $this->route('systemSetting');
                if (! $setting instanceof SystemSetting) {
                    return;
                }

                if ($setting->status !== 'draft') {
                    $validator->errors()->add('setting', 'Only draft compliance rule settings can be approved.');
                }

                if ($setting->created_by_user_id === $this->user()?->id) {
                    $validator->errors()->add('setting', 'The compliance rule creator cannot approve the same draft.');
                }

                $user = $this->user();
                if (! $user || ! app(CompanyScopeService::class)->allowsSettingMutation($user, $setting->company_id)) {
                    $validator->errors()->add('setting', 'The compliance setting is outside your company scope.');
                }
            },
        ];
    }
}
