<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HrHelpdeskTicket extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'raised_by_user_id',
        'assigned_to_user_id',
        'closed_by_user_id',
        'ticket_number',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'resolution_summary',
        'attachments',
        'workflow_history',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'workflow_history' => 'array',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function raisedBy(): BelongsTo { return $this->belongsTo(User::class, 'raised_by_user_id'); }
    public function assignedTo(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to_user_id'); }
    public function closedBy(): BelongsTo { return $this->belongsTo(User::class, 'closed_by_user_id'); }
}
