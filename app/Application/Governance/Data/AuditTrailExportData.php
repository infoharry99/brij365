<?php
namespace App\Application\Governance\Data;
final readonly class AuditTrailExportData { public function __construct(public string $csv, public string $filename = 'builder360-audit-trail.csv') {} }
