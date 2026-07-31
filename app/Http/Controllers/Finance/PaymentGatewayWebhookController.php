<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentRequestResource;
use App\Models\PaymentRequest;
use App\Services\Finance\PaymentRequestService;
use App\Support\MoneyInputPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Validator;

class PaymentGatewayWebhookController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_PAYLOAD_KEYS = [
        'gateway_reference',
        'status',
        'amount',
        'currency',
        'transaction_reference',
        'payment_mode',
        'gateway_response_code',
        'paid_at',
    ];

    public function __invoke(
        Request $request,
        PaymentRequestService $paymentRequestService,
        MoneyInputPolicy $moneyInputPolicy,
    ): JsonResponse
    {
        $secret = trim((string) config('builder360.integrations.payment_gateway.webhook_secret', ''));

        if ($secret === '') {
            return response()->json([
                'message' => 'Payment gateway webhook is not configured.',
            ], 503);
        }

        $signature = trim((string) $request->header('X-Builder360-Signature', ''));
        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if ($signature === '' || ! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid payment gateway webhook signature.',
            ], 401);
        }

        $unexpectedKeys = array_values(array_diff(array_keys($request->all()), self::ALLOWED_PAYLOAD_KEYS));

        if ($unexpectedKeys !== []) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => collect($unexpectedKeys)
                    ->mapWithKeys(fn (string $key): array => [$key => ['The selected field is not allowed for this payment gateway webhook.']])
                    ->all(),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'gateway_reference' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:paid,succeeded,success,captured'],
            'amount' => ['required', 'numeric', 'min:1', $moneyInputPolicy->paymentAmountMaxRule()],
            'currency' => ['required', 'string', 'size:3'],
            'transaction_reference' => ['required', 'string', 'max:160'],
            'payment_mode' => ['nullable', 'string', 'in:online,upi,card,netbanking,wallet'],
            'gateway_response_code' => ['nullable', 'string', 'max:80'],
            'paid_at' => ['nullable', 'date'],
        ])->validate();

        $paymentRequest = PaymentRequest::query()
            ->where('gateway_reference', $validated['gateway_reference'])
            ->firstOrFail();

        /** @var JsonResource $resource */
        $resource = new PaymentRequestResource(
            $paymentRequestService->markPaidFromGateway($paymentRequest, $validated, $request),
        );

        return $resource->response()->setStatusCode(200);
    }
}
