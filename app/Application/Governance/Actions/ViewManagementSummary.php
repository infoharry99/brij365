<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\ManagementSummaryData;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Governance\ManagementReportService;
use Illuminate\Http\Request;
final class ViewManagementSummary {
 public function __construct(private readonly ManagementReportService $reports,private readonly AuditLogger $audit) {}
 public function execute(User $actor,string $format,Request $request): ManagementSummaryData { $summary=$this->reports->summary($actor); $this->audit->record($actor,$format==='csv'?'governance.management_summary.exported':'governance.management_summary.viewed',$format==='csv'?'Exported management summary CSV':'Viewed management summary',null,['company_id'=>$summary['scope']['company_id']??null,'open_leads'=>$summary['crm']['open_leads']??null,'confirmed_bookings'=>$summary['sales']['confirmed_bookings']??null,'open_service_tickets'=>$summary['after_sales']['open_tickets']??null,'format'=>$format],$request); return new ManagementSummaryData($summary,$format,$format==='csv'?$this->reports->csv($this->reports->summaryRows($summary)):null); }
}
