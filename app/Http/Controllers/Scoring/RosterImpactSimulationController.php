<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Scoring\Actions\SimulateRosterImpact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\SimulateRosterImpactRequest;
use App\Models\AttendanceRotationRule;
use Illuminate\Http\RedirectResponse;

final class RosterImpactSimulationController extends Controller
{
    public function __invoke(
        AttendanceRotationRule $attendanceRotationRule,
        SimulateRosterImpactRequest $request,
        SimulateRosterImpact $simulate,
    ): RedirectResponse {
        $result = $simulate->execute($attendanceRotationRule, $request->simulationInput());

        return redirect()->route('scoring.index', ['view' => 'simulation'])
            ->with('status', 'Non-authoritative roster impact simulation completed. No roster, attendance, or payroll record was created or changed.')
            ->with('roster_simulation', $result->toView());
    }
}
