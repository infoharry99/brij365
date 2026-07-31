<?php

namespace App\Services\AfterSales;

use App\Models\Booking;
use App\Models\MaintenanceWorkOrder;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\NotificationCenterService;
use App\Services\Security\CompanyScopeService;
use App\Services\Settings\SystemSettingResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AfterSalesService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly NotificationCenterService $notifications,
        private readonly SystemSettingResolver $settings,
        private readonly CompanyScopeService $companyScope,
    )
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createTicket(array $data, User $actor, ?Request $request = null): ServiceTicket
    {
        return DB::transaction(function () use ($data, $actor, $request): ServiceTicket {
            $booking = Booking::query()
                ->with(['project', 'unit', 'customer'])
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanRaiseForBooking($actor, $booking);

            $priority = $data['priority'];
            $slaHours = $this->slaHours($priority, $booking->company_id);
            $openedAt = now();
            $slaDueAt = $openedAt->copy()->addHours($slaHours);

            $ticket = new ServiceTicket([
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'project_unit_id' => $booking->project_unit_id,
                'raised_by_user_id' => $actor->id,
                'ticket_number' => $this->nextTicketNumber(),
                'category' => $data['category'],
                'priority' => $priority,
                'source' => $data['source'] ?? ($this->isBuyerPortalUser($actor) ? 'portal' : 'internal'),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => 'open',
                'first_response_due_at' => $openedAt->copy()->addHours(min($slaHours, 24)),
                'sla_due_at' => $slaDueAt,
                'attachments' => $data['attachments'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('open', $actor, 'Service ticket raised'),
                ],
                'metadata' => [
                    'sla_hours' => $slaHours,
                    'booking_code' => $booking->booking_code,
                    'unit_code' => $booking->unit?->unit_code,
                ],
            ]);
            $ticket->created_at = $openedAt;
            $ticket->updated_at = $openedAt;
            $ticket->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.ticket.created',
                'Created after-sales service ticket',
                $ticket,
                ['ticket_number' => $ticket->ticket_number, 'booking_code' => $booking->booking_code, 'priority' => $priority],
                $request,
            );

            return $ticket->load($this->ticketRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function assignTicket(ServiceTicket $serviceTicket, array $data, User $actor, ?Request $request = null): ServiceTicket
    {
        return DB::transaction(function () use ($serviceTicket, $data, $actor, $request): ServiceTicket {
            $ticket = ServiceTicket::query()->whereKey($serviceTicket->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');

            if ($ticket->status === 'closed') {
                throw ValidationException::withMessages(['ticket' => 'Closed tickets cannot be reassigned.']);
            }

            $assignee = User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail();

            if ($assignee->company_id !== $ticket->company_id) {
                throw ValidationException::withMessages(['assigned_to_user_id' => 'The assignee must belong to the ticket company.']);
            }

            $history = $ticket->workflow_history ?? [];
            $history[] = $this->workflowEvent('assigned', $actor, $data['note'] ?? "Assigned to {$assignee->name}");

            $ticket->forceFill([
                'assigned_to_user_id' => $assignee->id,
                'status' => 'assigned',
                'first_responded_at' => $ticket->first_responded_at ?? now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.ticket.assigned',
                'Assigned after-sales service ticket',
                $ticket,
                ['ticket_number' => $ticket->ticket_number, 'assigned_to' => $assignee->email],
                $request,
            );

            if ($assignee->id !== $actor->id) {
                $this->notifications->sendToUser($assignee, [
                    'category' => 'after_sales',
                    'severity' => $ticket->priority === 'critical' ? 'critical' : 'warning',
                    'title' => "Service ticket {$ticket->ticket_number} assigned",
                    'body' => "{$ticket->subject} has been assigned to you for action.",
                    'action_url' => '/after-sales/tickets?assigned_to_user_id='.$assignee->id,
                    'payload' => [
                        'ticket_number' => $ticket->ticket_number,
                        'priority' => $ticket->priority,
                    ],
                ], $actor, $ticket);
            }

            return $ticket->load($this->ticketRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createWorkOrder(array $data, User $actor, ?Request $request = null): MaintenanceWorkOrder
    {
        return DB::transaction(function () use ($data, $actor, $request): MaintenanceWorkOrder {
            $ticket = ServiceTicket::query()
                ->whereKey($data['service_ticket_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $ticket->company_id, 'service_ticket_id');

            if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                throw ValidationException::withMessages(['service_ticket_id' => 'Work orders cannot be created for resolved or closed tickets.']);
            }

            if (! empty($data['assigned_to_user_id'])) {
                $assignee = User::query()->whereKey($data['assigned_to_user_id'])->firstOrFail();

                if ($assignee->company_id !== $ticket->company_id) {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'The assignee must belong to the ticket company.']);
                }
            }

            if (! empty($data['vendor_id'])) {
                $vendor = Vendor::query()->whereKey($data['vendor_id'])->firstOrFail();

                if ($vendor->company_id !== $ticket->company_id || $vendor->status !== 'active') {
                    throw ValidationException::withMessages(['vendor_id' => 'The selected vendor is not active for the ticket company.']);
                }
            }

            $workOrder = MaintenanceWorkOrder::create([
                'company_id' => $ticket->company_id,
                'service_ticket_id' => $ticket->id,
                'project_unit_id' => $ticket->project_unit_id,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? $ticket->assigned_to_user_id,
                'vendor_id' => $data['vendor_id'] ?? null,
                'work_order_number' => $this->nextWorkOrderNumber(),
                'status' => ! empty($data['scheduled_on']) ? 'scheduled' : 'planned',
                'scheduled_on' => $data['scheduled_on'] ?? null,
                'scope_of_work' => $data['scope_of_work'],
                'estimated_cost' => $data['estimated_cost'] ?? 0,
                'materials_required' => $data['materials_required'] ?? [],
                'workflow_history' => [
                    $this->workflowEvent('planned', $actor, 'Maintenance work order created'),
                ],
            ]);

            $history = $ticket->workflow_history ?? [];
            $history[] = $this->workflowEvent('in_progress', $actor, 'Maintenance work order created');

            $ticket->forceFill([
                'status' => 'in_progress',
                'assigned_to_user_id' => $workOrder->assigned_to_user_id ?? $ticket->assigned_to_user_id,
                'first_responded_at' => $ticket->first_responded_at ?? now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.work_order.created',
                'Created maintenance work order',
                $workOrder,
                ['ticket_number' => $ticket->ticket_number, 'work_order_number' => $workOrder->work_order_number],
                $request,
            );

            if ($workOrder->assigned_to_user_id !== null && $workOrder->assigned_to_user_id !== $actor->id) {
                $this->notifications->sendToUser($workOrder->assignedTo()->firstOrFail(), [
                    'category' => 'maintenance',
                    'severity' => $ticket->priority === 'critical' ? 'critical' : 'warning',
                    'title' => "Maintenance work order {$workOrder->work_order_number} created",
                    'body' => 'A maintenance work order has been created from service ticket '.$ticket->ticket_number.'.',
                    'action_url' => '/after-sales/work-orders?status='.$workOrder->status,
                    'payload' => [
                        'work_order_number' => $workOrder->work_order_number,
                        'ticket_number' => $ticket->ticket_number,
                    ],
                ], $actor, $workOrder);
            }

            return $workOrder->load($this->workOrderRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function completeWorkOrder(MaintenanceWorkOrder $maintenanceWorkOrder, array $data, User $actor, ?Request $request = null): MaintenanceWorkOrder
    {
        return DB::transaction(function () use ($maintenanceWorkOrder, $data, $actor, $request): MaintenanceWorkOrder {
            $workOrder = MaintenanceWorkOrder::query()->whereKey($maintenanceWorkOrder->id)->lockForUpdate()->firstOrFail();

            $this->assertCompanyScope($actor, $workOrder->company_id, 'work_order');

            if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
                throw ValidationException::withMessages(['work_order' => 'Completed or cancelled work orders cannot be completed again.']);
            }

            $history = $workOrder->workflow_history ?? [];
            $history[] = $this->workflowEvent('completed', $actor, $data['completion_notes']);

            $workOrder->forceFill([
                'status' => 'completed',
                'completion_notes' => $data['completion_notes'],
                'actual_cost' => $data['actual_cost'] ?? 0,
                'completed_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.work_order.completed',
                'Completed maintenance work order',
                $workOrder,
                ['work_order_number' => $workOrder->work_order_number],
                $request,
            );

            return $workOrder->load($this->workOrderRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function resolveTicket(ServiceTicket $serviceTicket, array $data, User $actor, ?Request $request = null): ServiceTicket
    {
        return DB::transaction(function () use ($serviceTicket, $data, $actor, $request): ServiceTicket {
            $ticket = ServiceTicket::query()
                ->withCount(['workOrders as open_work_orders_count' => fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled'])])
                ->whereKey($serviceTicket->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');

            if ((int) $ticket->open_work_orders_count > 0) {
                throw ValidationException::withMessages(['ticket' => 'All active maintenance work orders must be completed before ticket resolution.']);
            }

            $history = $ticket->workflow_history ?? [];
            $history[] = $this->workflowEvent('resolved', $actor, $data['resolution_summary']);

            $ticket->forceFill([
                'status' => 'resolved',
                'resolution_summary' => $data['resolution_summary'],
                'resolved_at' => now(),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.ticket.resolved',
                'Resolved after-sales service ticket',
                $ticket,
                ['ticket_number' => $ticket->ticket_number],
                $request,
            );

            return $ticket->load($this->ticketRelations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function closeTicket(ServiceTicket $serviceTicket, array $data, User $actor, ?Request $request = null): ServiceTicket
    {
        return DB::transaction(function () use ($serviceTicket, $data, $actor, $request): ServiceTicket {
            $ticket = ServiceTicket::query()->whereKey($serviceTicket->id)->lockForUpdate()->firstOrFail();

            $this->assertCanCloseTicket($actor, $ticket);

            if ($ticket->status !== 'resolved') {
                throw ValidationException::withMessages(['ticket' => 'Only resolved tickets can be closed.']);
            }

            $history = $ticket->workflow_history ?? [];
            $history[] = $this->workflowEvent('closed', $actor, $data['note'] ?? 'Service ticket closed');

            $ticket->forceFill([
                'status' => 'closed',
                'closed_by_user_id' => $actor->id,
                'closed_at' => now(),
                'customer_rating' => $data['customer_rating'] ?? $ticket->customer_rating,
                'scoring_inputs' => array_replace($ticket->scoring_inputs ?? [], $data['scoring_inputs'] ?? []),
                'workflow_history' => $history,
            ])->save();

            $this->auditLogger->record(
                $actor,
                'after_sales.ticket.closed',
                'Closed after-sales service ticket',
                $ticket,
                ['ticket_number' => $ticket->ticket_number, 'customer_rating' => $ticket->customer_rating],
                $request,
            );

            return $ticket->load($this->ticketRelations());
        });
    }

    /**
     * @return array<string, string>
     */
    private function workflowEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function slaHours(string $priority, int $companyId): int
    {
        $configuredHours = data_get($this->settings->value($companyId, 'after_sales.sla_hours'), $priority);

        if (is_numeric($configuredHours) && (int) $configuredHours > 0) {
            return (int) $configuredHours;
        }

        return (int) config("builder360.after_sales.sla_hours.{$priority}", 48);
    }

    private function assertCanRaiseForBooking(User $actor, Booking $booking): void
    {
        if (
            $this->isBuyerPortalUser($actor)
            && (int) $booking->customer?->portal_user_id === (int) $actor->id
        ) {
            return;
        }

        $this->assertCompanyScope($actor, $booking->company_id, 'booking_id');
    }

    private function assertCanCloseTicket(User $actor, ServiceTicket $ticket): void
    {
        if (
            $this->isBuyerPortalUser($actor)
            && $ticket->customer()->where('portal_user_id', $actor->id)->exists()
        ) {
            return;
        }

        $this->assertCompanyScope($actor, $ticket->company_id, 'ticket');
    }

    private function assertCompanyScope(User $actor, int|string|null $companyId, string $field): void
    {
        if ($this->companyScope->allows($actor, $companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'The selected record is outside your company scope.',
        ]);
    }

    private function isBuyerPortalUser(User $actor): bool
    {
        return $actor->role?->slug === 'buyer' && $actor->hasPermission('buyer.view');
    }

    private function nextTicketNumber(): string
    {
        return sprintf('AST-%04d', ServiceTicket::query()->withTrashed()->count() + 1001);
    }

    private function nextWorkOrderNumber(): string
    {
        return sprintf('MWO-%04d', MaintenanceWorkOrder::query()->withTrashed()->count() + 1001);
    }

    /**
     * @return array<int, string>
     */
    private function ticketRelations(): array
    {
        return ['booking', 'project', 'unit', 'customer', 'raisedBy', 'assignedTo', 'closedBy', 'workOrders.assignedTo', 'workOrders.vendor'];
    }

    /**
     * @return array<int, string>
     */
    private function workOrderRelations(): array
    {
        return ['serviceTicket', 'unit', 'assignedTo', 'vendor'];
    }
}
