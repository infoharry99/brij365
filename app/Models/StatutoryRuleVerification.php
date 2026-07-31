<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatutoryRuleVerification extends Model
{
    protected $fillable = [
        'system_setting_id',
        'company_id',
        'verified_by_user_id',
        'configuration_checksum',
        'attestation',
        'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function systemSetting(): BelongsTo
    {
        return $this->belongsTo(SystemSetting::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
