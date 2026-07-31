<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\Security\CompanyScopeService;
use Illuminate\Foundation\Http\FormRequest;

class VendorPerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor
            && $this->user()?->can('viewAny', PurchaseOrder::class) === true
            && app(CompanyScopeService::class)->allows($this->user(), $vendor->company_id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
