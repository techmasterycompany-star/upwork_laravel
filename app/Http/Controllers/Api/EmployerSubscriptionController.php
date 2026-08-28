<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployerSubscriptionController extends Controller
{
    public function availablePlans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Plan::all(),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $subscription = $request->user()->employerProfile
            ->subscriptions()
            ->where('status', 'active')
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => $subscription?->load('plan'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $employerProfile = $request->user()->employerProfile;

        // Cancel any existing active subscription before creating a new one
        $employerProfile->subscriptions()
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $periodEnd = $data['billing_cycle'] === 'monthly'
            ? now()->addMonth()
            : now()->addYear();

        // Status starts as "pending" — only the Stripe webhook (checkout.session.completed)
        // flips it to "active" once payment actually succeeds. This prevents a subscription
        // being marked active when the employer never completes checkout.
        $subscription = $employerProfile->subscriptions()->create([
            'plan_id' => $data['plan_id'],
            'billing_cycle' => $data['billing_cycle'],
            'status' => 'pending',
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription created. Proceed to payment to activate it.',
            'data' => $subscription->load('plan'),
        ], 201);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->employerProfile
            ->subscriptions()
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found.',
            ], 404);
        }

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully.',
        ]);
    }
}