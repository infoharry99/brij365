<?php

namespace App\Http\Requests\Hr;

use App\Domain\Hr\Services\HrReportCatalog;
use App\Support\QueryFilterPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class HrReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && app(HrReportCatalog::class)->for($actor) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                app(QueryFilterPolicy::class)->rejectUnexpected($validator, $this->query(), []);
            },
        ];
    }
}
