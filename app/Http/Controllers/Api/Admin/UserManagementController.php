<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuditLogger;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('is_blocked')) {
            $query->where('is_blocked', $request->boolean('is_blocked'));
        }

        $users = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

   public function block(User $user): JsonResponse
{
    $user->update(['is_blocked' => true]);

    AuditLogger::log(
        action: 'user_blocked',
        modelType: User::class,
        modelId: $user->id,
        newValues: ['is_blocked' => true]
    );

    return response()->json([
        'success' => true,
        'message' => 'User blocked successfully.',
    ]);
}

   public function unblock(User $user): JsonResponse
{
    $user->update(['is_blocked' => false]);

    AuditLogger::log(
        action: 'user_unblocked',
        modelType: User::class,
        modelId: $user->id,
        newValues: ['is_blocked' => false]
    );

    return response()->json([
        'success' => true,
        'message' => 'User unblocked successfully.',
    ]);
}
   public function destroy(Request $request, User $user): JsonResponse
{
    if ($request->user()->id === $user->id) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot delete your own account.',
        ], 403);
    }

    AuditLogger::log(
        action: 'user_deleted',
        modelType: User::class,
        modelId: $user->id,
        oldValues: ['name' => $user->name, 'email' => $user->email]
    );

    $user->delete();

    return response()->json([
        'success' => true,
        'message' => 'User deleted successfully.',
    ]);
}
}