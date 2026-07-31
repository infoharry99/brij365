<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'vendor_code',
        'name',
        'vendor_type',
        'contact_name',
        'email',
        'phone',
        'gstin',
        'pan',
        'pan_encrypted',
        'pan_last4',
        'address',
        'bank_details',
        'compliance_documents',
        'status',
        'metadata',
        'scoring_inputs',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'compliance_documents' => 'array',
            'metadata' => 'array',
            'scoring_inputs' => 'array',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function pan(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => $this->decryptString($attributes['pan_encrypted'] ?? null) ?? $value,
            set: function (?string $value): array {
                $normalized = $value !== null ? strtoupper(trim($value)) : null;

                if ($normalized === null || $normalized === '') {
                    return [
                        'pan' => null,
                        'pan_encrypted' => null,
                        'pan_last4' => null,
                    ];
                }

                return [
                    'pan' => null,
                    'pan_encrypted' => Crypt::encryptString($normalized),
                    'pan_last4' => substr($normalized, -4),
                ];
            },
        );
    }

    /**
     * @return Attribute<array<string, mixed>|null, array<string, mixed>|null>
     */
    protected function bankDetails(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?array => $this->decryptBankDetails($this->decodeBankDetails($value)),
            set: fn (?array $value): ?string => $value === null
                ? null
                : json_encode($this->encryptBankDetails($value), JSON_THROW_ON_ERROR),
        );
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    public function contractorMeasurements(): HasMany
    {
        return $this->hasMany(ContractorMeasurement::class);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBankDetails(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|null $details
     * @return array<string, mixed>|null
     */
    private function encryptBankDetails(?array $details): ?array
    {
        if ($details === null) {
            return null;
        }

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->encryptBankDetails($value);

                continue;
            }

            if ($this->isSensitiveBankKey($key) && is_scalar($value) && trim((string) $value) !== '') {
                $details[$key] = Crypt::encryptString((string) $value);
            }
        }

        return $details;
    }

    /**
     * @param array<string, mixed>|null $details
     * @return array<string, mixed>|null
     */
    private function decryptBankDetails(?array $details): ?array
    {
        if ($details === null) {
            return null;
        }

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->decryptBankDetails($value);

                continue;
            }

            if ($this->isSensitiveBankKey($key) && is_string($value)) {
                $details[$key] = $this->decryptString($value) ?? $value;
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

    private function decryptString(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }
}
