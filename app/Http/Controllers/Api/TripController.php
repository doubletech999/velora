<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Trip::with('user');

        // Filter by user if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('start_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('end_date', '<=', $request->end_date);
        }

        $trips = $query->orderBy('start_date', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $trips
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_name' => 'required|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string',
            'sites' => 'required|array|min:1',
            'sites.*' => 'exists:sites,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $trip = Trip::create([
            'user_id' => auth()->id(),
            'trip_name' => $request->trip_name,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'sites' => $request->sites
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Trip created successfully',
            'data' => $trip
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $trip = Trip::with('user')->find($id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found'
            ], 404);
        }

        // Load site details
        $siteIds = $trip->sites;
        $trip->site_details = \App\Models\Site::whereIn('id', $siteIds)->get();

        return response()->json([
            'success' => true,
            'data' => $trip
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $trip = Trip::find($id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found'
            ], 404);
        }

        // Check if user owns this trip
        if ($trip->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'trip_name' => 'string|max:255',
            'start_date' => 'date|after_or_equal:today',
            'end_date' => 'date|after:start_date',
            'description' => 'nullable|string',
            'sites' => 'array|min:1',
            'sites.*' => 'exists:sites,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $trip->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Trip updated successfully',
            'data' => $trip
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $trip = Trip::find($id);

        if (!$trip) {
            return response()->json([
                'success' => false,
                'message' => 'Trip not found'
            ], 404);
        }

        // Check if user owns this trip
        if ($trip->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $trip->delete();

        return response()->json([
            'success' => true,
            'message' => 'Trip deleted successfully'
        ]);
    }
}