<?php
namespace App\Application\Governance\Data;
final readonly class ManagementSummaryData { public function __construct(public array $summary,public string $format,public ?string $csv=null) {} }
