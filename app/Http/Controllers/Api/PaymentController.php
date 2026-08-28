<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
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

    public function history(Request $request): JsonResponse
    {
        $employerProfile = auth()->user()->employerProfile;

        if (!$employerProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found',
            ], 404);
        }

        $payments = Payment::whereHas('subscription', function ($q) use ($employerProfile) {
                $q->where('employer_id', $employerProfile->id);
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from, fn($q) => $q->whereDate('paid_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('paid_at', '<=', $request->to))
            ->latest('paid_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }
}