<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\AuditTrailExportData;
use App\Domain\Governance\Services\AuditTrailRegister;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Governance\ManagementReportService;
use Illuminate\Http\Request;
final class ExportAuditTrail {
 public function __construct(private readonly AuditTrailRegister $register,private readonly ManagementReportService $reports,private readonly AuditLogger $audit) {}
 public function execute(User $actor,array $filters,Request $request): AuditTrailExportData { unset($filters['page']); $events=$this->register->exportEvents($actor,$filters); $rows=$events->map(fn(AuditEvent $event):array=>['event_id'=>$event->id,'event_type'=>$event->event_type,'action'=>$event->action,'user_name'=>$event->user?->name,'user_email'=>$event->user?->email,'user_role'=>$event->user?->role?->slug,'auditable_type'=>$event->auditable_type,'auditable_id'=>$event->auditable_id,'request_method'=>$event->request_method,'request_path'=>$event->request_path,'request_id'=>$event->request_id,'ip_address'=>$event->ip_address,'created_at'=>$event->created_at?->toDateTimeString(),'metadata'=>$event->metadata??[]])->values()->all(); $safe=collect($filters)->only(['event_type','user_id','auditable_type','auditable_id','request_method','request_id','date_from','date_to','search'])->all(); $this->audit->record($actor,'governance.audit_events.exported','Exported governance audit trail',null,['format'=>'csv','row_count'=>count($rows),'filters'=>$safe],$request); return new AuditTrailExportData($this->reports->csv($rows)); }
}
