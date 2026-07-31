<?php

namespace App\Http\Requests\Hr;

use App\Domain\Payroll\Services\StatutoryRulePackDefinitionValidator;
use App\Domain\Scoring\Support\LogicCenterPermissions;
use App\Models\SystemSetting;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class VerifyStatutoryRulePackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $setting = $this->route('systemSetting');
        $user = $this->user();

        return $setting instanceof SystemSetting
            && $user !== null
            && ($user->hasPermission(LogicCenterPermissions::STATUTORY_VERIFY) || $user->hasPermission('compliance.manage'))
            && app(CompanyScopeService::class)->allowsSettingMutation($user, $setting->company_id);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'attestation' => ['required', 'string', 'min:20', 'max:2000'],
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
                    $validator->errors()->add('setting', 'Only draft statutory rule packs may be verified.');
                }

                if (data_get($setting->value, 'governed_statutory_pack_version') !== StatutoryRulePackDefinitionValidator::SCHEMA_VERSION) {
                    $validator->errors()->add('setting', 'Only governed statutory rule packs use independent source verification.');
                }

                if ($setting->created_by_user_id === $this->user()?->id) {
                    $validator->errors()->add('setting', 'The statutory pack creator cannot independently verify the same version.');
                }
            },
        ];
    }
}
