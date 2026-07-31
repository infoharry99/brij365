<?php
namespace App\Domain\Legal\Services;
use App\Models\ComplianceObligation;
use App\Models\Project;
use App\Models\ProjectApproval;
use App\Models\ReraRegistration;
use App\Models\User;
use App\Services\Security\CompanyScopeService;
use App\Support\PaginationPolicy;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
final class LegalComplianceRegister
{
    public function __construct(private readonly CompanyScopeService $scope,private readonly PaginationPolicy $pagination) {}
    public function registrations(User $actor,array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(ReraRegistration::query()->with(['project','createdBy','verifiedBy']),$actor)
            ->when($filters['project_id']??null,fn($q,int $id)=>$q->where('project_id',$id))->when($filters['status']??null,fn($q,string $s)=>$q->where('status',$s))
            ->when(array_key_exists('expires_within_days',$filters),fn($q)=>$q->whereDate('expires_on','<=',now()->addDays((int)$filters['expires_within_days'])->toDateString()))
            ->orderByRaw('expires_on IS NULL, expires_on ASC')->paginate($this->pagination->workspacePerPage($filters['per_page']??null))->withQueryString();
    }
    public function approvals(User $actor,array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(ProjectApproval::query()->with(['project','responsibleUser','verifiedBy']),$actor)
            ->when($filters['project_id']??null,fn($q,int $id)=>$q->where('project_id',$id))->when($filters['status']??null,fn($q,string $s)=>$q->where('status',$s))
            ->when($filters['approval_type']??null,fn($q,string $type)=>$q->where('approval_type',$type))
            ->when(array_key_exists('expires_within_days',$filters),fn($q)=>$q->whereDate('expires_on','<=',now()->addDays((int)$filters['expires_within_days'])->toDateString()))
            ->orderByRaw('expires_on IS NULL, expires_on ASC')->paginate($this->pagination->workspacePerPage($filters['per_page']??null))->withQueryString();
    }
    public function obligations(User $actor,array $filters): LengthAwarePaginator
    {
        return $this->scope->apply(ComplianceObligation::query()->with(['project','assignedTo','completedBy']),$actor)
            ->when($filters['project_id']??null,fn($q,int $id)=>$q->where('project_id',$id))->when($filters['status']??null,fn($q,string $s)=>$q->where('status',$s))
            ->when($filters['compliance_type']??null,fn($q,string $type)=>$q->where('compliance_type',$type))
            ->when(array_key_exists('due_within_days',$filters),fn($q)=>$q->whereDate('due_on','<=',now()->addDays((int)$filters['due_within_days'])->toDateString()))
            ->orderBy('due_on')->paginate($this->pagination->workspacePerPage($filters['per_page']??null))->withQueryString();
    }
    public function projects(User $actor): Collection { return $this->scope->apply(Project::query()->select(['id','company_id','code','name','status'])->where('status','active'),$actor)->orderBy('code')->get(); }
    public function assignees(User $actor): Collection { return $this->scope->apply(User::query()->select(['id','company_id','name','email','status'])->where('status','active'),$actor)->orderBy('name')->get(); }
}
