<?php

namespace App\Application\AfterSales\Actions;

use App\Application\AfterSales\Data\CloseServiceTicketData;
use App\Application\Scoring\Actions\RefreshCurrentScore;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\AfterSales\AfterSalesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class CloseServiceTicketAction
{
    public function __construct(
        private AfterSalesService $afterSales,
        private RefreshCurrentScore $refreshScore,
    ) {}

    public function execute(
        ServiceTicket $ticket,
        CloseServiceTicketData $data,
        User $actor,
        Request $request,
    ): ServiceTicket {
        return DB::transaction(function () use ($ticket, $data, $actor, $request): ServiceTicket {
            $closed = $this->afterSales->closeTicket($ticket, $data->toArray(), $actor, $request);
            $project = $closed->project()->first();
            if ($project !== null) {
                $this->refreshScore->executeWhenReady('customer_satisfaction', $project);
            }

            return $closed;
        });
    }
}
