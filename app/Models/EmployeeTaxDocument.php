<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeTaxDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'employee_id',
        'generated_by_user_id',
        'issued_by_user_id',
        'acknowledged_by_user_id',
        'document_number',
        'document_type',
        'financial_year',
        'assessment_year',
        'version',
        'status',
        'gross_salary',
        'taxable_income',
        'tds_deducted',
        'net_salary_paid',
        'payroll_run_ids',
        'component_summary',
        'tax_configuration_snapshot',
        'document_payload',
        'issue_reference',
        'employee_acknowledgement_note',
        'workflow_history',
        'generated_at',
        'issued_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'gross_salary' => 'decimal:2',
            'taxable_income' => 'decimal:2',
            'tds_deducted' => 'decimal:2',
            'net_salary_paid' => 'decimal:2',
            'payroll_run_ids' => 'array',
            'component_summary' => 'array',
            'tax_configuration_snapshot' => 'array',
            'document_payload' => 'encrypted:array',
            'workflow_history' => 'array',
            'generated_at' => 'datetime',
            'issued_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }
}
