<?php

namespace App\Http\Requests\Hr;

use App\Models\AttendancePeriodLock;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinalizeAttendancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendancePeriodLock::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:today'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            if (! $user) {
                return;
            }

            $companyId = $this->filled('company_id')
                ? $this->integer('company_id')
                : app(CompanyScopeService::class)->companyIdFor($user);

            if ($companyId === null || $companyId === 0) {
                $validator->errors()->add('company_id', 'A company assignment is required before finalizing attendance.');

                return;
            }

            if (! app(CompanyScopeService::class)->allows($user, $companyId)) {
                $validator->errors()->add('company_id', 'The selected company is outside your company scope.');
            }

            $periodStart = $this->date('period_start')->toDateString();
            $periodEnd = $this->date('period_end')->toDateString();

            $overlapExists = AttendancePeriodLock::query()
                ->where('company_id', $companyId)
                ->where('status', 'finalized')
                ->whereDate('period_start', '<=', $periodEnd)
                ->whereDate('period_end', '>=', $periodStart)
                ->where(function ($query) use ($periodStart, $periodEnd): void {
                    $query->whereDate('period_start', '!=', $periodStart)
                        ->orWhereDate('period_end', '!=', $periodEnd);
                })
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add('period_start', 'The selected dates overlap an attendance period that is already finalized.');
            }
        }];
    }
}
