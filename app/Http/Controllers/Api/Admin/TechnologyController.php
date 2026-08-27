<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Technology;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnologyController extends Controller
{
    public function index(): JsonResponse
    {
        $technologies = Technology::latest()->get();

        return response()->json([
            'success' => true,
            'data' => $technologies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:technologies,name',
        ]);

        $technology = Technology::create([
            'name' => $data['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Technology created successfully.',
            'data' => $technology,
        ], 201);
    }

    public function update(Request $request, Technology $technology): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:technologies,name,' . $technology->id,
        ]);

        $technology->update([
            'name' => $data['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Technology updated successfully.',
            'data' => $technology,
        ]);
    }

    public function destroy(Technology $technology): JsonResponse
    {
        $technology->delete();

        return response()->json([
            'success' => true,
            'message' => 'Technology deleted successfully.',
        ]);
    }
}