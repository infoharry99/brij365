<?php

namespace App\Http\Controllers\Procurement;

use App\Application\Procurement\Actions\UpdateVendorPerformanceEvidence;
use App\Application\Procurement\Data\VendorPerformanceEvidenceData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\UpdateVendorPerformanceEvidenceRequest;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;

final class VendorPerformanceScoreController extends Controller
{
    public function update(
        UpdateVendorPerformanceEvidenceRequest $request,
        Vendor $vendor,
        UpdateVendorPerformanceEvidence $action,
    ): RedirectResponse {
        $snapshot = $action->execute(
            $vendor,
            VendorPerformanceEvidenceData::from($request->validated()),
            $request->user(),
            $request,
        );

        return redirect()->route('procurement.vendors.index')->with(
            'status',
            'Vendor performance score updated to '.number_format((float) $snapshot->total_score, 2).'.',
        );
    }
}
