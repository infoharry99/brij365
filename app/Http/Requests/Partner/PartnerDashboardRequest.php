<?php

namespace App\Http\Requests\Partner;

use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PartnerDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->role?->scope_level === 'partner'
            && $user->can('partner.portal') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), ['limit']);
            },
        ];
    }
}
