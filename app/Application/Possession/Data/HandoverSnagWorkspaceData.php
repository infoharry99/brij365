<?php
namespace App\Application\Possession\Data;
use Illuminate\Pagination\LengthAwarePaginator; use Illuminate\Support\Collection;
final readonly class HandoverSnagWorkspaceData
{ public function __construct(public LengthAwarePaginator $snags,public array $filters,public Collection $handovers,public array $statuses,public array $severities,public array $abilities) {} public function toView(): array { return array_merge(get_object_vars($this),['canReportSnag'=>$this->abilities['canReportSnag']??false]); } }
