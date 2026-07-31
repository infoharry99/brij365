<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $sensitiveBankKeys = [
        'account_number',
        'account_no',
        'bank_account',
        'bank_account_number',
        'ifsc',
        'ifsc_code',
        'upi',
    ];

    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            $table->longText('pan_encrypted')->nullable()->after('pan');
            $table->string('pan_last4', 4)->nullable()->after('pan_encrypted')->index();
        });

        DB::table('vendors')
            ->orderBy('id')
            ->get(['id', 'pan', 'bank_details'])
            ->each(function (object $vendor): void {
                $updates = [];

                if (is_string($vendor->pan) && trim($vendor->pan) !== '') {
                    $pan = trim($vendor->pan);
                    $updates['pan_encrypted'] = Crypt::encryptString($pan);
                    $updates['pan_last4'] = substr($pan, -4);
                    $updates['pan'] = null;
                }

                $bankDetails = $this->decodeJson($vendor->bank_details);
                if ($bankDetails !== null) {
                    $updates['bank_details'] = json_encode($this->encryptBankDetails($bankDetails), JSON_THROW_ON_ERROR);
                }

                if ($updates !== []) {
                    DB::table('vendors')->where('id', $vendor->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        DB::table('vendors')
            ->orderBy('id')
            ->get(['id', 'pan_encrypted', 'bank_details'])
            ->each(function (object $vendor): void {
                $updates = [];

                if (is_string($vendor->pan_encrypted) && trim($vendor->pan_encrypted) !== '') {
                    $updates['pan'] = $this->decryptString($vendor->pan_encrypted);
                }

                $bankDetails = $this->decodeJson($vendor->bank_details);
                if ($bankDetails !== null) {
                    $updates['bank_details'] = json_encode($this->decryptBankDetails($bankDetails), JSON_THROW_ON_ERROR);
                }

                if ($updates !== []) {
                    DB::table('vendors')->where('id', $vendor->id)->update($updates);
                }
            });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropColumn(['pan_encrypted', 'pan_last4']);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function encryptBankDetails(array $details): array
    {
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->encryptBankDetails($value);

                continue;
            }

            if (is_string($key) && $this->isSensitiveBankKey($key) && is_scalar($value) && trim((string) $value) !== '') {
                $details[$key] = Crypt::encryptString((string) $value);
            }
        }

        return $details;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function decryptBankDetails(array $details): array
    {
        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $details[$key] = $this->decryptBankDetails($value);

                continue;
            }

            if (is_string($key) && $this->isSensitiveBankKey($key) && is_string($value)) {
                $details[$key] = $this->decryptString($value);
            }
        }

        return $details;
    }

    private function isSensitiveBankKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', trim($key)));

        return in_array($normalized, $this->sensitiveBankKeys, true);
    }

    private function decryptString(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
};
