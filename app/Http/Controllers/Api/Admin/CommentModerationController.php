<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;

class CommentModerationController extends Controller
{
    public function index(): JsonResponse
    {
        $comments = Comment::with(['user', 'job'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    public function hide(Comment $comment): JsonResponse
    {
        $comment->update([
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment hidden successfully.',
        ]);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment removed successfully.',
        ]);
    }
}