<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSavedSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = $request->user()->savedSearches()->latest()->get();

        return response()->json([
            'success' => true,
            'saved_searches' => $searches,
        ]);
    }

    public function store(StoreSavedSearchRequest $request): JsonResponse
    {
        $search = $request->user()->savedSearches()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Search saved successfully.',
            'saved_search' => $search,
        ], 201);
    }

    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this saved search.',
            ], 403);
        }

        $savedSearch->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved search deleted successfully.',
        ]);
    }
}