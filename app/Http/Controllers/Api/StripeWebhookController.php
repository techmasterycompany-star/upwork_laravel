<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: invalid payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: invalid signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutSessionCompleted($event->data->object);
        } else {
            Log::info('Stripe webhook: unhandled event type', ['type' => $event->type]);
        }

        return response()->json(['received' => true]);
    }

    private function handleCheckoutSessionCompleted($session): void
    {
        $subscriptionId = $session->metadata->subscription_id ?? null;

        if (! $subscriptionId) {
            Log::warning('Stripe webhook: no subscription_id in metadata', ['session_id' => $session->id]);
            return;
        }

        $subscription = Subscription::find($subscriptionId);

        if (! $subscription) {
            Log::warning('Stripe webhook: subscription not found', ['subscription_id' => $subscriptionId]);
            return;
        }

        // idempotency: لو نفس الـ payment_intent اتسجل قبل كده منسجلوش تاني
        if (Payment::where('gateway_transaction_id', $session->payment_intent)->exists()) {
            return;
        }

        Payment::create([
            'subscription_id' => $subscription->id,
            'amount' => $session->amount_total / 100,
            'currency' => strtoupper($session->currency),
            'gateway' => 'stripe',
            'gateway_transaction_id' => $session->payment_intent,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $subscription->update(['status' => 'active']);
    }
}