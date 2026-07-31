<?php

namespace App\Observers;

use App\Events\Task\WorkTaskChanged;
use App\Models\WorkTask;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class WorkTaskObserver implements ShouldHandleEventsAfterCommit
{
    public function created(WorkTask $task): void { WorkTaskChanged::dispatch($task, 'created'); }
    public function updated(WorkTask $task): void { WorkTaskChanged::dispatch($task, 'updated'); }
    public function deleted(WorkTask $task): void { WorkTaskChanged::dispatch($task, 'deleted'); }
}
