<?php
namespace App\Application\Approvals\Actions;
use App\Application\Approvals\Data\ApprovalCenterContextData;
use App\Application\Approvals\Data\ApprovalCenterExportData;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Builder360\ApprovalCenterService;
use Illuminate\Http\Request;
final class ExportApprovalCenter {
 public function __construct(private readonly ApprovalCenterService $approvals,private readonly AuditLogger $audit) {}
 public function execute(User $actor,ApprovalCenterContextData $context,Request $request): ApprovalCenterExportData { $rows=$this->approvals->exportRows($actor,$context->roleSlug,$context->projectId,$context->filters); $this->audit->record($actor,'approval_center.exported','Exported approval records',null,['row_count'=>count($rows),'filters'=>$context->filters],$request); return new ApprovalCenterExportData($rows); }
}
