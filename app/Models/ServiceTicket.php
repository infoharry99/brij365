<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceTicket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'booking_id',
        'customer_id',
        'project_unit_id',
        'raised_by_user_id',
        'assigned_to_user_id',
        'closed_by_user_id',
        'ticket_number',
        'category',
        'priority',
        'source',
        'subject',
        'description',
        'status',
        'first_response_due_at',
        'first_responded_at',
        'sla_due_at',
        'resolved_at',
        'closed_at',
        'resolution_summary',
        'customer_rating',
        'attachments',
        'workflow_history',
        'metadata',
        'scoring_inputs',
    ];

    protected function casts(): array
    {
        return [
            'first_response_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'customer_rating' => 'integer',
            'attachments' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'scoring_inputs' => 'array',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProjectUnit::class, 'project_unit_id');
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(MaintenanceWorkOrder::class);
    }
}
