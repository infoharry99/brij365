<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_code' => $this->vendor_code,
            'name' => $this->name,
            'vendor_type' => $this->vendor_type,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gstin' => $this->gstin,
            'pan' => $this->maskedIdentifier($this->pan_last4 ?: $this->pan),
            'pan_last4' => $this->pan_last4 ?: ($this->pan ? substr($this->pan, -4) : null),
            'address' => $this->address ?? [],
            'bank_details' => $this->maskedBankDetails($this->bank_details ?? []),
            'compliance_documents' => $this->compliance_documents ?? [],
            'status' => $this->status,
        ];
    }

    private function maskedIdentifier(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtoupper(trim($value));
        $last4 = strlen($normalized) <= 4 ? $normalized : substr($normalized, -4);

        return '******'.$last4;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function maskedBankDetails(array $details): array
    {
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->maskedBankDetails($value);

                continue;
            }

            if ($this->isSensitiveBankKey($key) && is_scalar($value)) {
                $details[$key] = $this->maskedIdentifier((string) $value);
            }
        }

        return $details;
    }

    private function isSensitiveBankKey(int|string $key): bool
    {
        if (! is_string($key)) {
            return false;
        }

        $normalized = strtolower(str_replace(['-', ' '], '_', trim($key)));

        return in_array($normalized, [
            'account_number',
            'account_no',
            'bank_account',
            'bank_account_number',
            'ifsc',
            'ifsc_code',
            'upi',
        ], true);
    }
}
