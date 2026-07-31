<?php

namespace App\Http\Requests\Scoring;

use App\Application\Scoring\DTOs\RosterImpactSimulationInputData;
use App\Domain\Hr\Services\AttendanceRosterRulePackResolver;
use App\Domain\Scoring\Services\LogicCenterAccessService;
use App\Models\AttendanceRotationRule;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

final class SimulateRosterImpactRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $rotation = $this->route('attendanceRotationRule');
        if ($user === null || ! $rotation instanceof AttendanceRotationRule) {
            return false;
        }

        $access = app(LogicCenterAccessService::class);

        return $access->canViewSection($user, 'simulation')
            && ($access->capabilities($user)['manageRosters'] ?? false)
            && app(CompanyScopeService::class)->allows($user, $rotation->company_id)
            && $user->can('view', $rotation);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendance_rotation_rule_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rotation = $this->route('attendanceRotationRule');
            if (! $rotation instanceof AttendanceRotationRule) {
                return;
            }

            if ((int) $this->input('attendance_rotation_rule_id') !== (int) $rotation->id) {
                $validator->errors()->add('attendance_rotation_rule_id', 'The selected rotation does not match this simulation request.');

                return;
            }

            if ($rotation->status !== 'active') {
                $validator->errors()->add('attendance_rotation_rule_id', 'Only an active rotation can be simulated.');

                return;
            }

            $start = Carbon::parse((string) $this->input('start_date'));
            $end = Carbon::parse((string) $this->input('end_date'));
            $rules = app(AttendanceRosterRulePackResolver::class)->resolve((int) $rotation->company_id, $start);
            $maximum = min(
                max(1, (int) $rotation->generation_horizon_days),
                max(1, $rules->maximumRotationGenerationHorizonDays),
            );
            if ($start->diffInDays($end) + 1 > $maximum) {
                $validator->errors()->add('end_date', 'The simulation range may not exceed the governed generation horizon of '.$maximum.' days.');
            }
        }];
    }

    public function simulationInput(): RosterImpactSimulationInputData
    {
        return new RosterImpactSimulationInputData(
            startDate: Carbon::parse((string) $this->validated('start_date'))->toDateString(),
            endDate: Carbon::parse((string) $this->validated('end_date'))->toDateString(),
        );
    }
}
