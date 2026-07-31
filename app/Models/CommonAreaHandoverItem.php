<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommonAreaHandoverItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'society_formation_id',
        'responsible_user_id',
        'signed_off_by_user_id',
        'item_number',
        'facility_name',
        'category',
        'checklist_total',
        'checklist_completed',
        'status',
        'target_completion_on',
        'signed_off_on',
        'snag_summary',
        'workflow_history',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'target_completion_on' => 'date',
            'signed_off_on' => 'date',
            'snag_summary' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function societyFormation(): BelongsTo
    {
        return $this->belongsTo(SocietyFormation::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function signedOffBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_off_by_user_id');
    }
}
