<?php

namespace Tests\Feature;

use App\Application\Payroll\Data\PayrollCommandData;
use App\Application\Payroll\Data\PayrollWorkspaceData;
use ReflectionClass;
use Tests\TestCase;

class PayrollApplicationLayerTest extends TestCase
{
    public function test_payroll_command_and_workspace_data_are_immutable(): void
    {
        foreach ([PayrollCommandData::class, PayrollWorkspaceData::class] as $class) {
            $this->assertTrue((new ReflectionClass($class))->isReadOnly(), $class.' must remain immutable.');
        }
    }

    public function test_payroll_controller_uses_focused_actions_without_queries_or_services(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Payroll/PayrollController.php'));
        foreach (['ListPayrollWorkspace $workspace', 'ListPayrollComponents $list', 'ListSalaryStructures $list', 'ListPayrollRuns $list', 'GeneratePayrollRun $generate', 'ApprovePayrollRun $approve', 'ListPayrollBankBatches $list', 'PreparePayrollBankBatch $prepare', 'ReleasePayrollBankBatch $release'] as $boundary) {
            $this->assertStringContainsString($boundary, $controller);
        }
        $this->assertStringNotContainsString('::query()', $controller);
        $this->assertStringNotContainsString('PayrollRunService $', $controller);
        $this->assertStringNotContainsString('PayrollBankTransferService $', $controller);
        $this->assertStringNotContainsString('CompanyScopeService $', $controller);
    }

    public function test_commission_and_tax_document_controllers_use_focused_actions(): void
    {
        $commission = file_get_contents(app_path('Http/Controllers/Payroll/CommissionController.php'));
        $tax = file_get_contents(app_path('Http/Controllers/Payroll/TaxDocumentController.php'));

        foreach (['ListCommissionRules $list', 'CreateCommissionRule $create', 'ListCommissionRuns $list', 'GenerateCommissionRun $generate', 'ApproveCommissionRun $approve', 'RejectCommissionRun $reject'] as $boundary) {
            $this->assertStringContainsString($boundary, $commission);
        }
        foreach (['ListTaxDocuments $list', 'GenerateTaxDocument $generate', 'IssueTaxDocument $issue', 'AcknowledgeTaxDocument $acknowledge'] as $boundary) {
            $this->assertStringContainsString($boundary, $tax);
        }
        $this->assertStringNotContainsString('::query()', $commission.$tax);
        $this->assertStringNotContainsString('Service $service', $commission.$tax);
    }
}
