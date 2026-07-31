<?php

namespace App\Http\Controllers\Scoring;

use App\Application\Hr\Actions\CreateAttendanceRosterRulePackDraft;
use App\Application\Hr\Data\HrCommandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scoring\StoreAttendanceRosterRulePackRequest;
use Illuminate\Http\RedirectResponse;

final class AttendanceRosterRulePackController extends Controller
{
    public function __invoke(
        StoreAttendanceRosterRulePackRequest $request,
        CreateAttendanceRosterRulePackDraft $create,
    ): RedirectResponse {
        $setting = $create->execute(new HrCommandData(
            $request->normalizedPayload(),
            $request->user(),
            $request,
        ));

        return redirect()->route('scoring.index', ['view' => 'roster'])
            ->with('status', "Governed {$setting->label} draft v{$setting->version} created. A different authorized user must approve it before the effective rules can change.");
    }
}
