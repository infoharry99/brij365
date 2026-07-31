<?php

namespace App\Http\Requests\Hr;

use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class HrDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasPermission('*')
            || $user->hasPermission('hr.view')
            || $user->hasPermission('hr.manage')
        );
    }

    public function rules(): array
    {
        return [];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), []);
            },
        ];
    }
}
