<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class PaymentController extends Controller
{
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $subscription = Subscription::with('plan')->findOrFail($data['subscription_id']);

        // Determine the price based on billing cycle
        $amount = $subscription->billing_cycle === 'monthly'
            ? $subscription->plan->price_monthly
            : $subscription->plan->price_yearly;

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $subscription->plan->name . ' (' . $subscription->billing_cycle . ')',
                    ],
                    'unit_amount' => (int) ($amount * 100), // Stripe expects amount in cents
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'subscription_id' => $subscription->id,
            ],
            'success_url' => config('app.frontend_url') . '/payment/success',
            'cancel_url' => config('app.frontend_url') . '/payment/cancel',
        ]);

        return response()->json([
            'success' => true,
            'checkout_url' => $session->url,
        ]);
    }
}