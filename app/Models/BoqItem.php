<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoqItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'construction_milestone_id',
        'vendor_id',
        'created_by_user_id',
        'boq_code',
        'trade',
        'description',
        'unit',
        'planned_quantity',
        'rate',
        'budget_amount',
        'measured_quantity',
        'certified_quantity',
        'certified_amount',
        'status',
        'specifications',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:3',
            'rate' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'measured_quantity' => 'decimal:3',
            'certified_quantity' => 'decimal:3',
            'certified_amount' => 'decimal:2',
            'specifications' => 'array',
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

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ConstructionMilestone::class, 'construction_milestone_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
