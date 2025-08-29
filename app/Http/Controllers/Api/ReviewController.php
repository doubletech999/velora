<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'site', 'guide']);

        // Filter by site if provided
        if ($request->has('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        // Filter by guide if provided
        if ($request->has('guide_id')) {
            $query->where('guide_id', $request->guide_id);
        }

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by rating if provided
        if ($request->has('rating')) {
            $query->where('rating', $request->rating);
        }

        $reviews = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'nullable|exists:sites,id',
            'guide_id' => 'nullable|exists:guides,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Must review either a site or a guide, not both or neither
        if ((!$request->site_id && !$request->guide_id) || ($request->site_id && $request->guide_id)) {
            return response()->json([
                'success' => false,
                'message' => 'You must review either a site or a guide, not both'
            ], 422);
        }

        // Check if user already reviewed this site/guide
        $existingReview = Review::where('user_id', $request->user()->id)
            ->where(function($query) use ($request) {
                if ($request->site_id) {
                    $query->where('site_id', $request->site_id);
                }
                if ($request->guide_id) {
                    $query->where('guide_id', $request->guide_id);
                }
            })
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this item'
            ], 422);
        }

        $review = Review::create([
            'user_id' => $request->user()->id,
            'site_id' => $request->site_id,
            'guide_id' => $request->guide_id,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);

        $review->load(['user', 'site', 'guide']);

        return response()->json([
            'success' => true,
            'message' => 'Review created successfully',
            'data' => $review
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $review = Review::with(['user', 'site', 'guide'])->find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $review
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        // Check if the user owns this review
        if ($review->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $review->update($request->only(['rating', 'comment']));
        $review->load(['user', 'site', 'guide']);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'data' => $review
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found'
            ], 404);
        }

        $request = request();
        
        // Check if the user owns this review or is an admin
        if ($review->user_id !== $request->user()->id && $request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own reviews'
            ], 403);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }
}