<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataImportBatch extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_CRM_PROSPECT_INQUIRIES = 'crm_prospect_inquiries';
    public const TYPE_HR_EMPLOYEES = 'hr_employees';

    public const STATUS_PREVIEW = 'preview';
    public const STATUS_POSTED = 'posted';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'posted_by_user_id',
        'import_number',
        'import_type',
        'source_filename',
        'checksum',
        'status',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'source_rows',
        'preview_rows',
        'error_report',
        'reconciliation_summary',
        'workflow_history',
        'metadata',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'source_rows' => 'array',
            'preview_rows' => 'array',
            'error_report' => 'array',
            'reconciliation_summary' => 'array',
            'workflow_history' => 'array',
            'metadata' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
