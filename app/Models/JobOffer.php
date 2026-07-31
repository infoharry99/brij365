<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobOffer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'candidate_id',
        'created_by_user_id',
        'released_by_user_id',
        'accepted_by_user_id',
        'offer_number',
        'template_code',
        'offered_ctc',
        'joining_date',
        'placeholders',
        'status',
        'released_at',
        'accepted_at',
        'document_history',
    ];

    protected function casts(): array
    {
        return [
            'offered_ctc' => 'decimal:2',
            'joining_date' => 'date',
            'placeholders' => 'array',
            'released_at' => 'datetime',
            'accepted_at' => 'datetime',
            'document_history' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }
}
