<?php

namespace Tests\Feature;

use App\Domain\Hr\Services\AttendanceRosterRulePackResolver;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRosterEntry;
use App\Models\AttendanceRotationRule;
use App\Models\AttendanceShift;
use App\Models\Employee;
use App\Models\PayrollAttendanceSnapshot;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RosterImpactSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_hr_user_can_preview_rotation_without_mutating_authoritative_records(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rotation = $this->rotation($hr);
        $start = $rotation->anchor_date->toDateString();
        $payload = [
            'attendance_rotation_rule_id' => $rotation->id,
            'start_date' => $start,
            'end_date' => $rotation->anchor_date->copy()->addDays(2)->toDateString(),
        ];
        $before = [
            AttendanceRosterEntry::query()->count(),
            AttendanceRecord::query()->count(),
            PayrollAttendanceSnapshot::query()->count(),
            PayrollRun::query()->count(),
        ];

        $response = $this->actingAs($hr)
            ->post(route('scoring.roster-simulations.store', $rotation), $payload)
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHas('status')
            ->assertSessionHas('roster_simulation', function (array $result) use ($rotation): bool {
                return $result['rotation_rule_id'] === $rotation->id
                    && $result['counts']['days'] === 3
                    && $result['counts']['shift_days'] === 2
                    && $result['counts']['off_days'] === 1
                    && $result['mutated_records'] === 0
                    && count($result['days']) === 3
                    && strlen($result['input_hash']) === 64
                    && strlen($result['result_hash']) === 64;
            });

        $this->assertSame($before, [
            AttendanceRosterEntry::query()->count(),
            AttendanceRecord::query()->count(),
            PayrollAttendanceSnapshot::query()->count(),
            PayrollRun::query()->count(),
        ]);
        $this->assertSame(1, AttendanceRotationRule::query()->whereKey($rotation->id)->count());
        $this->assertSame(0, $response->getSession()->get('roster_simulation')['mutated_records']);
    }

    public function test_same_rotation_and_range_produce_identical_hashes(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rotation = $this->rotation($hr);
        $payload = [
            'attendance_rotation_rule_id' => $rotation->id,
            'start_date' => $rotation->anchor_date->toDateString(),
            'end_date' => $rotation->anchor_date->copy()->addDays(4)->toDateString(),
        ];

        $first = $this->actingAs($hr)->post(route('scoring.roster-simulations.store', $rotation), $payload);
        $second = $this->actingAs($hr)->post(route('scoring.roster-simulations.store', $rotation), $payload);

        $this->assertSame(
            $first->getSession()->get('roster_simulation')['input_hash'],
            $second->getSession()->get('roster_simulation')['input_hash'],
        );
        $this->assertSame(
            $first->getSession()->get('roster_simulation')['result_hash'],
            $second->getSession()->get('roster_simulation')['result_hash'],
        );
    }

    public function test_range_beyond_governed_horizon_is_rejected(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rotation = $this->rotation($hr, 3);

        $this->from(route('scoring.index', ['view' => 'simulation']))
            ->actingAs($hr)
            ->post(route('scoring.roster-simulations.store', $rotation), [
                'attendance_rotation_rule_id' => $rotation->id,
                'start_date' => $rotation->anchor_date->toDateString(),
                'end_date' => $rotation->anchor_date->copy()->addDays(3)->toDateString(),
            ])
            ->assertRedirect(route('scoring.index', ['view' => 'simulation']))
            ->assertSessionHasErrors('end_date')
            ->assertSessionMissing('roster_simulation');
    }

    public function test_payroll_only_user_cannot_run_roster_simulation_by_direct_request(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $payroll = User::query()->where('email', 'kavita.shah@builder360.test')->firstOrFail();
        $rotation = $this->rotation($hr);

        $this->actingAs($payroll)
            ->post(route('scoring.roster-simulations.store', $rotation), [
                'attendance_rotation_rule_id' => $rotation->id,
                'start_date' => $rotation->anchor_date->toDateString(),
                'end_date' => $rotation->anchor_date->copy()->addDay()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_simulation_page_renders_authorized_rotation_and_safety_guard(): void
    {
        $this->seed();
        $hr = User::query()->where('email', 'deepa.rao@builder360.test')->firstOrFail();
        $rotation = $this->rotation($hr);

        $this->actingAs($hr)
            ->get(route('scoring.index', ['view' => 'simulation']))
            ->assertOk()
            ->assertSee('Roster generation impact')
            ->assertSee($rotation->name)
            ->assertSee('attendance_rotation_rule_id', false)
            ->assertSee('cannot create, publish, lock, or change a roster');
    }

    private function rotation(User $creator, int $horizon = 14): AttendanceRotationRule
    {
        $employee = Employee::query()->where('employee_code', 'EMP-0030')->firstOrFail();
        $shift = AttendanceShift::query()->where('code', 'GEN')->firstOrFail();
        $anchor = Carbon::parse('2026-08-03');
        $rules = app(AttendanceRosterRulePackResolver::class)->resolve((int) $employee->company_id, $anchor);

        return AttendanceRotationRule::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'name' => 'Non-mutating simulation rotation',
            'anchor_date' => $anchor->toDateString(),
            'cycle_days' => 2,
            'pattern' => [
                ['type' => 'shift', 'attendance_shift_id' => $shift->id],
                ['type' => 'off', 'attendance_shift_id' => null],
            ],
            'generation_horizon_days' => $horizon,
            'rule_context' => [
                'pinned_at' => now()->toISOString(),
                'packs' => $rules->ruleContext,
                'effective_values' => $rules->effectiveRosterValues(),
            ],
            'status' => 'active',
            'created_by_user_id' => $creator->id,
            'lock_version' => 1,
        ]);
    }
}
