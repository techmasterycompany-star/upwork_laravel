<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreCommentReportRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\JobListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
  
    public function store(StoreCommentRequest $request, JobListing $job): JsonResponse
    {
        $comment = $job->comments()->create([
            'user_id' => $request->user()->id,
            'content' => $request->validated('content'),
            'is_approved' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment posted successfully.',
            'comment' => $comment->load('user:id,name'),
        ], 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorizeOwnership($request, $comment);

        $comment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'comment' => $comment,
        ]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $this->authorizeOwnership($request, $comment);

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function report(StoreCommentReportRequest $request, Comment $comment): JsonResponse
    {
        $alreadyReported = $comment->reports()
            ->where('reported_by', $request->user()->id)
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this comment.',
            ], 409);
        }

        $report = $comment->reports()->create([
            'reported_by' => $request->user()->id,
            'reason' => $request->validated('reason'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment reported. Our team will review it.',
            'report' => $report,
        ], 201);
    }

    protected function authorizeOwnership(Request $request, Comment $comment): void
    {
        abort_unless(
            $comment->user_id === $request->user()->id,
            403,
            'You are not authorized to modify this comment.'
        );
    }
}