<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaypalPaymentController extends Controller
{
    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $subscription = Subscription::with('plan')->findOrFail($data['subscription_id']);

        $amount = $subscription->billing_cycle === 'monthly'
            ? $subscription->plan->price_monthly
            : $subscription->plan->price_yearly;

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $order = $provider->createOrder([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $subscription->id,
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format((float) $amount, 2, '.', ''),
                ],
                'description' => $subscription->plan->name . ' (' . $subscription->billing_cycle . ')',
            ]],
            'application_context' => [
                'return_url' => config('app.frontend_url') . '/payment/success',
                'cancel_url' => config('app.frontend_url') . '/payment/cancel',
            ],
        ]);

        if (!isset($order['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create PayPal order.',
                'error' => $order,
            ], 500);
        }

        $approveLink = collect($order['links'])->firstWhere('rel', 'approve')['href'] ?? null;

        return response()->json([
            'success' => true,
            'order_id' => $order['id'],
            'approve_url' => $approveLink,
        ]);
    }

    public function captureOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|string',
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $result = $provider->capturePaymentOrder($data['order_id']);

        if (($result['status'] ?? null) !== 'COMPLETED') {
            $subscription = Subscription::find($data['subscription_id']);
            if ($subscription) {
                $this->notifyEmployerOfFailedPayment($subscription);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed.',
                'error' => $result,
            ], 422);
        }

        $subscription = Subscription::findOrFail($data['subscription_id']);

        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? null;

        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $capture['amount']['value'] ?? 0,
            'currency' => $capture['amount']['currency_code'] ?? 'USD',
            'gateway' => 'paypal',
            'gateway_transaction_id' => $capture['id'] ?? $data['order_id'],
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $subscription->update(['status' => 'active']);

        $this->notifyEmployerOfSuccessfulPayment($subscription, $payment);

        return response()->json([
            'success' => true,
            'message' => 'Payment captured, subscription activated.',
            'data' => $subscription->fresh('plan'),
        ]);
    }

    /**
     * Issue #39: notify the employer when their payment succeeds.
     */
    private function notifyEmployerOfSuccessfulPayment(Subscription $subscription, Payment $payment): void
    {
        $subscription->loadMissing('employer.user', 'plan');

        if (! $subscription->employer || ! $subscription->employer->user) {
            return;
        }

        $planName = $subscription->plan->name ?? 'your plan';

        NotificationService::send(
            user: $subscription->employer->user,
            type: 'payment_success',
            title: 'Payment successful',
            content: "Your payment of {$payment->amount} {$payment->currency} for \"{$planName}\" was processed successfully via PayPal.",
            data: [
                'payment_id'      => $payment->id,
                'subscription_id' => $subscription->id,
                'gateway'         => 'paypal',
            ],
        );
    }

    /**
     * Issue #39: notify the employer when their PayPal payment fails to complete.
     */
    private function notifyEmployerOfFailedPayment(Subscription $subscription): void
    {
        $subscription->loadMissing('employer.user', 'plan');

        if (! $subscription->employer || ! $subscription->employer->user) {
            return;
        }

        $planName = $subscription->plan->name ?? 'your plan';

        NotificationService::send(
            user: $subscription->employer->user,
            type: 'payment_failed',
            title: 'Payment failed',
            content: "Your payment for \"{$planName}\" via PayPal was not completed.",
            data: [
                'subscription_id' => $subscription->id,
                'gateway'         => 'paypal',
            ],
        );
    }
}