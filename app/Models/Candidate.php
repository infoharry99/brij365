<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_opening_id',
        'owner_user_id',
        'employee_id',
        'candidate_code',
        'name',
        'email',
        'phone',
        'source',
        'current_company',
        'experience_years',
        'current_ctc',
        'expected_ctc',
        'notice_period_days',
        'skills',
        'documents',
        'stage',
        'status',
        'stage_history',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'experience_years' => 'decimal:2',
            'current_ctc' => 'decimal:2',
            'expected_ctc' => 'decimal:2',
            'notice_period_days' => 'integer',
            'skills' => 'array',
            'documents' => 'array',
            'stage_history' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class);
    }
}
