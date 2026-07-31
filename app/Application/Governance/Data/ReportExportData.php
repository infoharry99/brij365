<?php
namespace App\Application\Governance\Data;
final readonly class ReportExportData { public function __construct(public string $body,public string $contentType,public string $extension) {} }
