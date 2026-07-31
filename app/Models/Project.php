<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'project_type',
        'city',
        'state',
        'status',
        'budget_amount',
        'target_roi_percent',
        'starts_on',
        'ends_on',
        'scoring_inputs',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'target_roi_percent' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'scoring_inputs' => 'array',
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function teamAssignments(): HasMany
    {
        return $this->hasMany(ProjectTeamAssignment::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function prospectInquiries(): HasMany
    {
        return $this->hasMany(ProspectInquiry::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProjectUnit::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function collectionReceipts(): HasMany
    {
        return $this->hasMany(CollectionReceipt::class);
    }

    public function managedDocuments(): HasMany
    {
        return $this->hasMany(ManagedDocument::class, 'owner_id')->where('owner_type', 'project');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(BoqItem::class);
    }

    public function contractorMeasurements(): HasMany
    {
        return $this->hasMany(ContractorMeasurement::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function constructionMilestones(): HasMany
    {
        return $this->hasMany(ConstructionMilestone::class);
    }

    public function gstEntries(): HasMany
    {
        return $this->hasMany(GstEntry::class);
    }

    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }
}
