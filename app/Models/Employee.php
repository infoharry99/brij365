<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'project_id',
        'user_id',
        'manager_employee_id',
        'employee_code',
        'name',
        'designation',
        'department',
        'grade',
        'employment_type',
        'status',
        'joined_on',
        'statutory_state',
        'monthly_ctc',
        'sensitive_profile',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'monthly_ctc' => 'decimal:2',
            'sensitive_profile' => 'encrypted:array',
            'lock_version' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function projectTeamAssignments(): HasMany
    {
        return $this->hasMany(ProjectTeamAssignment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_employee_id');
    }

    public function managedDocuments(): HasMany
    {
        return $this->hasMany(ManagedDocument::class, 'owner_id')->where('owner_type', 'employee');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(EmployeeLeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveEncashments(): HasMany
    {
        return $this->hasMany(LeaveEncashment::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(EmployeeShiftAssignment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendanceRegularizationRequests(): HasMany
    {
        return $this->hasMany(AttendanceRegularizationRequest::class);
    }

    public function rosterEntries(): HasMany
    {
        return $this->hasMany(AttendanceRosterEntry::class);
    }

    public function salaryAssignments(): HasMany
    {
        return $this->hasMany(SalaryAssignment::class);
    }

    public function payrollRunItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function commissionItems(): HasMany
    {
        return $this->hasMany(CommissionItem::class);
    }

    public function taxDocuments(): HasMany
    {
        return $this->hasMany(EmployeeTaxDocument::class);
    }

    public function taxProfiles(): HasMany
    {
        return $this->hasMany(EmployeeTaxProfile::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function confirmationCases(): HasMany
    {
        return $this->hasMany(EmployeeConfirmationCase::class);
    }

    public function separationSettlements(): HasMany
    {
        return $this->hasMany(EmployeeSeparationSettlement::class);
    }

    public function exitInterviews(): HasMany
    {
        return $this->hasMany(EmployeeExitInterview::class);
    }

    public function managedConfirmationCases(): HasMany
    {
        return $this->hasMany(EmployeeConfirmationCase::class, 'manager_employee_id');
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(EmployeeLoan::class);
    }

    public function hrHelpdeskTickets(): HasMany
    {
        return $this->hasMany(HrHelpdeskTicket::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    public function profileSections(): HasMany
    {
        return $this->hasMany(EmployeeProfileSection::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class);
    }

    public function managedPerformanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'manager_employee_id');
    }
}
