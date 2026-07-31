<?php
namespace App\Application\Governance\Actions;
use App\Application\Governance\Data\ReportExportData;
use App\Application\Governance\Data\ReportRegisterData;
use App\Services\Governance\ManagementReportService;
final class RenderReportExport {
 public function __construct(private readonly ManagementReportService $reports) {}
 public function execute(ReportRegisterData $page): ReportExportData { return match($page->format){'excel'=>new ReportExportData($this->reports->excelXml($page->rows,$page->report),'application/vnd.ms-excel; charset=UTF-8','xls'),'pdf'=>new ReportExportData($this->reports->pdf($page->rows,'Builder360 '.str_replace('_',' ',$page->report).' report'),'application/pdf','pdf'),default=>new ReportExportData($this->reports->csv($page->rows),'text/csv; charset=UTF-8','csv')}; }
}
