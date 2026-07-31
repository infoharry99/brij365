<?php

namespace App\Application\Hr\Actions;

use App\Application\Hr\Data\HrCommandData;
use App\Models\LeaveProcessingRun;
use App\Services\Hr\LeaveProcessingService;

final class PostLeaveProcessingRun
{
    public function __construct(private readonly LeaveProcessingService $s) {}

    public function execute(LeaveProcessingRun $r, HrCommandData $c): LeaveProcessingRun
    {
        return $this->s->postProcessingRun($r, $c->actor, $c->request, $c->attributes['note'] ?? null);
    }
}
