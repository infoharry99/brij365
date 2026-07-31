<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\BookingPaymentSchedule;
use App\Models\CollectionReceipt;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\CompanyScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRequestService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, User $actor, ?Request $request = null): PaymentRequest
    {
        return DB::transaction(function () use ($data, $actor, $request): PaymentRequest {
            $booking = Booking::query()
                ->whereKey($data['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $booking->company_id)) {
                throw ValidationException::withMessages([
                    'booking_id' => 'The booking is outside your company scope.',
                ]);
            }

            $schedule = isset($data['booking_payment_schedule_id'])
                ? BookingPaymentSchedule::query()
                    ->whereKey($data['booking_payment_schedule_id'])
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $this->assertActiveBooking($booking);
            $this->assertOutstandingCapacity($booking, $schedule, (float) $data['amount']);
            $this->assertNoActiveScheduleRequest($schedule);

            $requestNumber = $this->nextRequestNumber();
            $gatewayReference = $this->nextGatewayReference($requestNumber);
            $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addDays(7);
            $checksum = $this->checksum($gatewayReference, (float) $data['amount'], $booking->id, $schedule?->id);
            $gatewayProvider = $this->configuredGatewayProvider();

            $paymentRequest = PaymentRequest::create([
                'company_id' => $booking->company_id,
                'project_id' => $booking->project_id,
                'booking_id' => $booking->id,
                'booking_payment_schedule_id' => $schedule?->id,
                'customer_id' => $booking->customer_id,
                'created_by_user_id' => $actor->id,
                'request_number' => $requestNumber,
                'gateway_provider' => $gatewayProvider,
                'gateway_reference' => $gatewayReference,
                'status' => 'requested',
                'amount' => $data['amount'],
                'currency' => 'INR',
                'purpose' => $data['purpose'],
                'expires_at' => $expiresAt,
                'checksum' => $checksum,
                'gateway_payload' => [
                    'provider' => $gatewayProvider,
                    'payment_url' => route('buyer.payment-requests.pay', ['paymentRequest' => '__PAYMENT_REQUEST_ID__'], false),
                    'currency' => 'INR',
                    'amount' => (float) $data['amount'],
                    'expires_at' => $expiresAt->toISOString(),
                    'checksum' => $checksum,
                    'simulation_notice' => 'Internal simulated payment link; no external gateway is invoked.',
                ],
                'workflow_history' => [
                    $this->historyEvent('requested', $actor, 'Payment request created for buyer payment link.'),
                ],
                'metadata' => array_merge($data['metadata'] ?? [], [
                    'created_from' => 'finance_payment_request_service',
                    'booking_code' => $booking->booking_code,
                    'schedule_sequence' => $schedule?->sequence,
                ]),
            ]);

            $paymentRequest->forceFill([
                'gateway_payload' => array_merge($paymentRequest->gateway_payload ?? [], [
                    'payment_url' => route('buyer.payment-requests.pay', $paymentRequest, false),
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.payment_request.created',
                'Created buyer payment request',
                $paymentRequest,
                [
                    'request_number' => $paymentRequest->request_number,
                    'booking_code' => $booking->booking_code,
                    'amount' => $paymentRequest->amount,
                ],
                $request,
            );

            return $paymentRequest->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function markPaid(PaymentRequest $paymentRequest, array $data, User $actor, ?Request $request = null): PaymentRequest
    {
        return DB::transaction(function () use ($paymentRequest, $data, $actor, $request): PaymentRequest {
            $lockedRequest = PaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(CompanyScopeService::class)->allows($actor, $lockedRequest->company_id)) {
                throw ValidationException::withMessages([
                    'payment_request' => 'The selected payment request is outside your company scope.',
                ]);
            }

            if ($lockedRequest->status !== 'requested') {
                throw ValidationException::withMessages([
                    'payment_request' => 'Only requested payment links can be paid.',
                ]);
            }

            $this->assertSimulatedGatewayPaymentAllowed($lockedRequest);

            if ($lockedRequest->expires_at && $lockedRequest->expires_at->isPast()) {
                $lockedRequest->forceFill([
                    'status' => 'expired',
                    'workflow_history' => array_merge($lockedRequest->workflow_history ?? [], [
                        $this->historyEvent('expired', $actor, 'Payment request expired before simulated payment.'),
                    ]),
                ])->save();

                throw ValidationException::withMessages([
                    'payment_request' => 'This payment link has expired.',
                ]);
            }

            $booking = Booking::query()
                ->whereKey($lockedRequest->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule = $lockedRequest->booking_payment_schedule_id
                ? BookingPaymentSchedule::query()
                    ->whereKey($lockedRequest->booking_payment_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $this->assertOutstandingCapacity($booking, $schedule, (float) $lockedRequest->amount);

            $receipt = CollectionReceipt::create([
                'company_id' => $lockedRequest->company_id,
                'project_id' => $lockedRequest->project_id,
                'booking_id' => $lockedRequest->booking_id,
                'booking_payment_schedule_id' => $lockedRequest->booking_payment_schedule_id,
                'customer_id' => $lockedRequest->customer_id,
                'collected_by_user_id' => $actor->id,
                'approved_by_user_id' => null,
                'receipt_number' => $this->nextReceiptNumber(),
                'status' => 'approved',
                'receipt_date' => now()->toDateString(),
                'payment_mode' => $data['payment_mode'],
                'instrument_number' => $data['instrument_number'],
                'bank_name' => 'Internal Simulated Gateway',
                'amount' => $lockedRequest->amount,
                'tax_deducted_amount' => 0,
                'notes' => 'Auto-approved receipt created from simulated buyer payment request.',
                'metadata' => [
                    'source' => 'payment_request',
                    'payment_request_id' => $lockedRequest->id,
                    'payment_request_number' => $lockedRequest->request_number,
                    'gateway_reference' => $lockedRequest->gateway_reference,
                    'gateway_response_code' => $data['gateway_response_code'] ?? 'SIMULATED_SUCCESS',
                    'auto_approved_gateway' => true,
                ],
                'approved_at' => now(),
            ]);

            if ($schedule) {
                $this->refreshScheduleStatus($schedule);
            }

            $lockedRequest->forceFill([
                'status' => 'paid',
                'paid_by_user_id' => $actor->id,
                'collection_receipt_id' => $receipt->id,
                'paid_at' => now(),
                'payment_mode' => $data['payment_mode'],
                'instrument_number' => $data['instrument_number'],
                'gateway_payload' => array_merge($lockedRequest->gateway_payload ?? [], [
                    'gateway_response_code' => $data['gateway_response_code'] ?? 'SIMULATED_SUCCESS',
                    'paid_at' => now()->toISOString(),
                ]),
                'workflow_history' => array_merge($lockedRequest->workflow_history ?? [], [
                    $this->historyEvent('paid', $actor, 'Buyer simulated online payment succeeded and collection receipt was created.'),
                ]),
                'metadata' => array_merge($lockedRequest->metadata ?? [], [
                    'collection_receipt_number' => $receipt->receipt_number,
                    'paid_from' => 'buyer_portal',
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.payment_request.paid',
                'Paid buyer payment request',
                $lockedRequest,
                [
                    'request_number' => $lockedRequest->request_number,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $lockedRequest->amount,
                ],
                $request,
            );

            $this->auditLogger->record(
                $actor,
                'finance.collection.gateway_auto_approved',
                'Auto-approved collection receipt from simulated gateway payment',
                $receipt,
                [
                    'request_number' => $lockedRequest->request_number,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $receipt->amount,
                ],
                $request,
            );

            return $lockedRequest->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function markPaidFromGateway(PaymentRequest $paymentRequest, array $data, ?Request $request = null): PaymentRequest
    {
        return DB::transaction(function () use ($paymentRequest, $data, $request): PaymentRequest {
            $lockedRequest = PaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status === 'paid') {
                $this->auditLogger->record(
                    null,
                    'finance.payment_request.gateway_webhook_idempotent',
                    'Received idempotent paid payment gateway webhook',
                    $lockedRequest,
                    [
                        'request_number' => $lockedRequest->request_number,
                        'gateway_reference' => $lockedRequest->gateway_reference,
                        'transaction_reference' => $data['transaction_reference'] ?? null,
                    ],
                    $request,
                );

                return $lockedRequest->load($this->relations());
            }

            if ($lockedRequest->status !== 'requested') {
                throw ValidationException::withMessages([
                    'payment_request' => 'Only requested payment links can be reconciled from gateway webhooks.',
                ]);
            }

            $this->assertRealGatewayWebhookAllowed($lockedRequest);
            $this->assertGatewayAmountMatches($lockedRequest, $data);

            if ($lockedRequest->expires_at && $lockedRequest->expires_at->isPast()) {
                $lockedRequest->forceFill([
                    'status' => 'expired',
                    'workflow_history' => array_merge($lockedRequest->workflow_history ?? [], [
                        $this->systemHistoryEvent('expired', 'Payment request expired before gateway webhook reconciliation.'),
                    ]),
                ])->save();

                throw ValidationException::withMessages([
                    'payment_request' => 'This payment link has expired.',
                ]);
            }

            $booking = Booking::query()
                ->whereKey($lockedRequest->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $schedule = $lockedRequest->booking_payment_schedule_id
                ? BookingPaymentSchedule::query()
                    ->whereKey($lockedRequest->booking_payment_schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail()
                : null;

            $this->assertOutstandingCapacity($booking, $schedule, (float) $lockedRequest->amount);

            $provider = strtolower(trim((string) $lockedRequest->gateway_provider));
            $transactionReference = (string) ($data['transaction_reference'] ?? $lockedRequest->gateway_reference);
            $paymentMode = (string) ($data['payment_mode'] ?? 'online');
            $paidAt = isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : now();

            $receipt = CollectionReceipt::create([
                'company_id' => $lockedRequest->company_id,
                'project_id' => $lockedRequest->project_id,
                'booking_id' => $lockedRequest->booking_id,
                'booking_payment_schedule_id' => $lockedRequest->booking_payment_schedule_id,
                'customer_id' => $lockedRequest->customer_id,
                'collected_by_user_id' => null,
                'approved_by_user_id' => null,
                'receipt_number' => $this->nextReceiptNumber(),
                'status' => 'approved',
                'receipt_date' => $paidAt->toDateString(),
                'payment_mode' => $paymentMode,
                'instrument_number' => $transactionReference,
                'bank_name' => strtoupper($provider).' Gateway',
                'amount' => $lockedRequest->amount,
                'tax_deducted_amount' => 0,
                'notes' => 'Auto-approved receipt created from verified payment gateway webhook.',
                'metadata' => [
                    'source' => 'payment_gateway_webhook',
                    'payment_request_id' => $lockedRequest->id,
                    'payment_request_number' => $lockedRequest->request_number,
                    'gateway_provider' => $provider,
                    'gateway_reference' => $lockedRequest->gateway_reference,
                    'gateway_response_code' => $data['gateway_response_code'] ?? null,
                    'gateway_status' => $data['status'],
                    'auto_approved_gateway' => true,
                ],
                'approved_at' => now(),
            ]);

            if ($schedule) {
                $this->refreshScheduleStatus($schedule);
            }

            $lockedRequest->forceFill([
                'status' => 'paid',
                'paid_by_user_id' => null,
                'collection_receipt_id' => $receipt->id,
                'paid_at' => $paidAt,
                'payment_mode' => $paymentMode,
                'instrument_number' => $transactionReference,
                'gateway_payload' => array_merge($lockedRequest->gateway_payload ?? [], [
                    'gateway_response_code' => $data['gateway_response_code'] ?? null,
                    'gateway_status' => $data['status'],
                    'transaction_reference' => $transactionReference,
                    'webhook_received_at' => now()->toISOString(),
                    'paid_at' => $paidAt->toISOString(),
                ]),
                'workflow_history' => array_merge($lockedRequest->workflow_history ?? [], [
                    $this->systemHistoryEvent('paid', 'Verified payment gateway webhook reconciled payment and created collection receipt.'),
                ]),
                'metadata' => array_merge($lockedRequest->metadata ?? [], [
                    'collection_receipt_number' => $receipt->receipt_number,
                    'paid_from' => 'payment_gateway_webhook',
                ]),
            ])->save();

            $this->auditLogger->record(
                null,
                'finance.payment_request.gateway_paid',
                'Reconciled payment gateway webhook',
                $lockedRequest,
                [
                    'request_number' => $lockedRequest->request_number,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $lockedRequest->amount,
                    'gateway_provider' => $provider,
                    'transaction_reference' => $transactionReference,
                ],
                $request,
            );

            $this->auditLogger->record(
                null,
                'finance.collection.gateway_auto_approved',
                'Auto-approved collection receipt from verified gateway webhook',
                $receipt,
                [
                    'request_number' => $lockedRequest->request_number,
                    'receipt_number' => $receipt->receipt_number,
                    'amount' => $receipt->amount,
                    'gateway_provider' => $provider,
                ],
                $request,
            );

            return $lockedRequest->load($this->relations());
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function cancel(PaymentRequest $paymentRequest, array $data, User $actor, ?Request $request = null): PaymentRequest
    {
        return DB::transaction(function () use ($paymentRequest, $data, $actor, $request): PaymentRequest {
            $lockedRequest = PaymentRequest::query()
                ->whereKey($paymentRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->status !== 'requested') {
                throw ValidationException::withMessages([
                    'payment_request' => 'Only requested payment links can be cancelled.',
                ]);
            }

            $lockedRequest->forceFill([
                'status' => 'cancelled',
                'workflow_history' => array_merge($lockedRequest->workflow_history ?? [], [
                    $this->historyEvent('cancelled', $actor, $data['reason']),
                ]),
                'metadata' => array_merge($lockedRequest->metadata ?? [], [
                    'cancelled_reason' => $data['reason'],
                    'cancelled_by_user_id' => $actor->id,
                    'cancelled_at' => now()->toISOString(),
                ]),
            ])->save();

            $this->auditLogger->record(
                $actor,
                'finance.payment_request.cancelled',
                'Cancelled buyer payment request',
                $lockedRequest,
                [
                    'request_number' => $lockedRequest->request_number,
                    'reason' => $data['reason'],
                ],
                $request,
            );

            return $lockedRequest->load($this->relations());
        });
    }

    private function assertActiveBooking(Booking $booking): void
    {
        if (! in_array($booking->status, ['confirmed', 'agreement_pending', 'registered'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Payment requests can be created only for active confirmed bookings.',
            ]);
        }
    }

    private function assertOutstandingCapacity(
        Booking $booking,
        ?BookingPaymentSchedule $schedule,
        float $amount,
    ): void {
        $bookingOutstanding = max((float) $booking->net_receivable - (float) CollectionReceipt::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->sum('amount'), 0);

        if ($amount > $bookingOutstanding) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount exceeds the outstanding booking receivable.',
            ]);
        }

        if (! $schedule) {
            return;
        }

        if ($schedule->booking_id !== $booking->id) {
            throw ValidationException::withMessages([
                'booking_payment_schedule_id' => 'The selected payment schedule does not belong to this booking.',
            ]);
        }

        $scheduleOutstanding = max((float) $schedule->amount - (float) CollectionReceipt::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->whereIn('status', ['submitted', 'approved'])
            ->sum('amount'), 0);

        if ($amount > $scheduleOutstanding) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount exceeds the selected payment schedule outstanding amount.',
            ]);
        }
    }

    private function assertNoActiveScheduleRequest(?BookingPaymentSchedule $schedule): void
    {
        if (! $schedule) {
            return;
        }

        $exists = PaymentRequest::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->where('status', 'requested')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'booking_payment_schedule_id' => 'An active payment request already exists for this schedule.',
            ]);
        }
    }

    private function refreshScheduleStatus(BookingPaymentSchedule $schedule): void
    {
        $approvedTotal = (float) CollectionReceipt::query()
            ->where('booking_payment_schedule_id', $schedule->id)
            ->where('status', 'approved')
            ->sum('amount');

        $status = match (true) {
            $approvedTotal <= 0 => 'pending',
            $approvedTotal >= (float) $schedule->amount => 'paid',
            default => 'partially_paid',
        };

        $schedule->forceFill(['status' => $status])->save();
    }

    private function nextRequestNumber(): string
    {
        return sprintf('PAYREQ-%05d', PaymentRequest::query()->withTrashed()->count() + 10001);
    }

    private function configuredGatewayProvider(): string
    {
        $provider = strtolower(trim((string) config('builder360.integrations.payment_gateway.provider', 'prototype')));

        return $provider !== '' ? $provider : 'prototype';
    }

    private function assertSimulatedGatewayPaymentAllowed(PaymentRequest $paymentRequest): void
    {
        $configuredProvider = $this->configuredGatewayProvider();
        $requestProvider = strtolower(trim((string) $paymentRequest->gateway_provider));

        if (
            ! $this->isPrototypeGatewayProvider($configuredProvider)
            || ! $this->isPrototypeGatewayProvider($requestProvider)
        ) {
            throw ValidationException::withMessages([
                'payment_request' => 'Direct simulated buyer payment is disabled for configured real payment gateway providers.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertGatewayAmountMatches(PaymentRequest $paymentRequest, array $data): void
    {
        $payloadCurrency = strtoupper(trim((string) ($data['currency'] ?? '')));

        if ($payloadCurrency !== $paymentRequest->currency) {
            throw ValidationException::withMessages([
                'currency' => 'Gateway webhook currency does not match the payment request.',
            ]);
        }

        if (round((float) $data['amount'], 2) !== round((float) $paymentRequest->amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => 'Gateway webhook amount does not match the payment request.',
            ]);
        }
    }

    private function assertRealGatewayWebhookAllowed(PaymentRequest $paymentRequest): void
    {
        $configuredProvider = $this->configuredGatewayProvider();
        $requestProvider = strtolower(trim((string) $paymentRequest->gateway_provider));

        if ($this->isPrototypeGatewayProvider($configuredProvider) || $this->isPrototypeGatewayProvider($requestProvider)) {
            throw ValidationException::withMessages([
                'payment_request' => 'Payment gateway webhooks require a configured real payment gateway provider.',
            ]);
        }

        if ($configuredProvider !== $requestProvider) {
            throw ValidationException::withMessages([
                'gateway_provider' => 'Configured payment gateway provider does not match the payment request provider.',
            ]);
        }
    }

    private function isPrototypeGatewayProvider(string $provider): bool
    {
        return $provider === ''
            || in_array($provider, ['prototype', 'demo', 'mock', 'sandbox', 'simulated', 'simulation'], true);
    }

    private function nextReceiptNumber(): string
    {
        return sprintf('RCPT-%04d', CollectionReceipt::query()->withTrashed()->count() + 1001);
    }

    private function nextGatewayReference(string $requestNumber): string
    {
        return 'B360PAY-'.str_replace('PAYREQ-', '', $requestNumber).'-'.now()->format('YmdHis');
    }

    private function checksum(string $gatewayReference, float $amount, int $bookingId, ?int $scheduleId): string
    {
        return hash('sha256', implode('|', [
            $gatewayReference,
            number_format($amount, 2, '.', ''),
            $bookingId,
            $scheduleId ?? 'booking',
        ]));
    }

    private function historyEvent(string $status, User $actor, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => $actor->id,
            'actor' => $actor->name,
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    private function systemHistoryEvent(string $status, string $note): array
    {
        return [
            'status' => $status,
            'actor_user_id' => null,
            'actor' => 'Payment Gateway',
            'note' => $note,
            'at' => now()->toISOString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return ['company', 'project', 'booking', 'paymentSchedule', 'customer', 'collectionReceipt', 'createdBy', 'paidBy'];
    }
}
