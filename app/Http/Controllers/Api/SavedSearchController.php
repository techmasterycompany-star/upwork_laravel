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

public function destroy(Request $request, $id): JsonResponse
{
    $savedSearch = $request->user()
        ->savedSearches()
        ->find($id);

    if (!$savedSearch) {
        return response()->json([
            'success' => false,
            'message' => 'Saved search not found.',
        ], 404);
    }

    $savedSearch->delete();

    return response()->json([
        'success' => true,
        'message' => 'Saved search deleted successfully.',
    ]);
}
}