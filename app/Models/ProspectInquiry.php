<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProspectInquiry extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_QUALIFIED = 'qualified';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CLOSED_UNQUALIFIED = 'closed_unqualified';
    public const STATUS_CLOSED_DUPLICATE = 'closed_duplicate';

    /**
     * @var array<int, string>
     */
    public const OPEN_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_ASSIGNED,
        self::STATUS_CONTACTED,
        self::STATUS_QUALIFIED,
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'assigned_to_user_id',
        'converted_lead_id',
        'duplicate_of_id',
        'inquiry_number',
        'name',
        'email',
        'phone',
        'source',
        'channel',
        'preferred_contact_method',
        'status',
        'budget_min',
        'budget_max',
        'message',
        'consent_to_contact',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'metadata',
        'assigned_at',
        'converted_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'consent_to_contact' => 'boolean',
            'metadata' => 'array',
            'assigned_at' => 'datetime',
            'converted_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function convertedLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'converted_lead_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONVERTED,
            self::STATUS_CLOSED_DUPLICATE,
            self::STATUS_CLOSED_UNQUALIFIED,
        ], true);
    }
}
