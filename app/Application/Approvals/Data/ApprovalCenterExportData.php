<?php
namespace App\Application\Approvals\Data;
final readonly class ApprovalCenterExportData { public function __construct(public array $rows, public string $filename='builder360-approval-center.csv') {} }
