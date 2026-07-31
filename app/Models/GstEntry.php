<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GstEntry extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by_user_id',
        'approved_by_user_id',
        'source_type',
        'source_id',
        'entry_number',
        'period_year',
        'period_month',
        'document_date',
        'document_number',
        'party_name',
        'party_gstin',
        'place_of_supply_state',
        'transaction_type',
        'hsn_sac',
        'tax_rate',
        'taxable_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'cess_amount',
        'total_tax_amount',
        'status',
        'metadata',
        'workflow_history',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'tax_rate' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'cess_amount' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'metadata' => 'array',
            'workflow_history' => 'array',
            'approved_at' => 'datetime',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
