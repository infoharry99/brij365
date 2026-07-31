<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceWorkOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'service_ticket_id',
        'project_unit_id',
        'assigned_to_user_id',
        'vendor_id',
        'work_order_number',
        'status',
        'scheduled_on',
        'scope_of_work',
        'estimated_cost',
        'actual_cost',
        'materials_required',
        'completion_notes',
        'completed_at',
        'workflow_history',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
            'materials_required' => 'array',
            'completed_at' => 'datetime',
            'workflow_history' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
