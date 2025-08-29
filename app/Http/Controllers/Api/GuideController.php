<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuideController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Guide::with('user');

        // Filter by language if provided
        if ($request->has('language')) {
            $query->where('languages', 'like', '%' . $request->language . '%');
        }

        // Filter by approval status
        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->is_approved);
        }

        $guides = $query->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $guides
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bio' => 'nullable|string',
            'languages' => 'required|string',
            'phone' => 'required|string|max:20',
            'hourly_rate' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user is already a guide
        $existingGuide = Guide::where('user_id', auth()->id())->first();
        if ($existingGuide) {
            return response()->json([
                'success' => false,
                'message' => 'You are already registered as a guide'
            ], 400);
        }

        $guide = Guide::create([
            'user_id' => auth()->id(),
            'bio' => $request->bio,
            'languages' => $request->languages,
            'phone' => $request->phone,
            'hourly_rate' => $request->hourly_rate,
            'is_approved' => false // Needs admin approval
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Guide profile created successfully. Pending admin approval.',
            'data' => $guide->load('user')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $guide = Guide::with(['user', 'reviews', 'bookings'])->find($id);

        if (!$guide) {
            return response()->json([
                'success' => false,
                'message' => 'Guide not found'
            ], 404);
        }

        // Calculate average rating
        $averageRating = $guide->reviews()->avg('rating');
        $guide->average_rating = round($averageRating, 1);

        return response()->json([
            'success' => true,
            'data' => $guide
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'success' => false,
                'message' => 'Guide not found'
            ], 404);
        }

        // Check if user owns this guide profile
        if ($guide->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bio' => 'nullable|string',
            'languages' => 'string',
            'phone' => 'string|max:20',
            'hourly_rate' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $guide->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Guide profile updated successfully',
            'data' => $guide->load('user')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $guide = Guide::find($id);

        if (!$guide) {
            return response()->json([
                'success' => false,
                'message' => 'Guide not found'
            ], 404);
        }

        // Check if user owns this guide profile or is admin
        if ($guide->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $guide->delete();

        return response()->json([
            'success' => true,
            'message' => 'Guide profile deleted successfully'
        ]);
    }
}