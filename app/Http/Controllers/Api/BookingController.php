<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BookingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'guide.user']);

        // If user is not admin, show only their bookings
        if (auth()->user()->role !== 'admin') {
            if (auth()->user()->role === 'guide') {
                // If user is a guide, show bookings for their guide profile
                $guide = auth()->user()->guide;
                if ($guide) {
                    $query->where('guide_id', $guide->id);
                }
            } else {
                // Regular user - show only their bookings
                $query->where('user_id', auth()->id());
            }
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('booking_date', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('booking_date', '<=', $request->end_date);
        }

        $bookings = $query->orderBy('booking_date', 'desc')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guide_id' => 'required|exists:guides,id',
            'booking_date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get guide information
        $guide = Guide::findOrFail($request->guide_id);

        // Check if guide is approved
        if (!$guide->is_approved) {
            return response()->json([
                'success' => false,
                'message' => 'This guide is not approved for bookings'
            ], 422);
        }

        // Calculate total hours and price
        $startTime = Carbon::createFromFormat('H:i', $request->start_time);
        $endTime = Carbon::createFromFormat('H:i', $request->end_time);
        $totalHours = $endTime->diffInHours($startTime);
        $totalPrice = $totalHours * $guide->hourly_rate;

        // Check for existing bookings at the same time
        $existingBooking = Booking::where('guide_id', $request->guide_id)
            ->where('booking_date', $request->booking_date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                      ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                      ->orWhere(function ($q) use ($request) {
                          $q->where('start_time', '<=', $request->start_time)
                            ->where('end_time', '>=', $request->end_time);
                      });
            })->exists();

        if ($existingBooking) {
            return response()->json([
                'success' => false,
                'message' => 'Guide is not available at this time'
            ], 422);
        }

        $booking = Booking::create([
            'user_id' => auth()->id(),
            'guide_id' => $request->guide_id,
            'booking_date' => $request->booking_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'total_price' => $totalPrice,
            'status' => 'pending',
            'notes' => $request->notes
        ]);

        $booking->load(['user', 'guide.user']);

        return response()->json([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => $booking
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $booking = Booking::with(['user', 'guide.user'])->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        // Check authorization
        $user = auth()->user();
        if ($user->role !== 'admin' && 
            $booking->user_id !== $user->id && 
            ($user->guide && $booking->guide_id !== $user->guide->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this booking'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $booking
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        $user = auth()->user();

        // Check authorization based on user role
        if ($user->role === 'admin') {
            // Admin can update any booking
            $validator = Validator::make($request->all(), [
                'status' => 'in:pending,confirmed,cancelled,completed',
                'notes' => 'nullable|string|max:500'
            ]);
        } elseif ($booking->user_id === $user->id) {
            // User can only cancel their own booking if it's pending
            if ($booking->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify booking with current status'
                ], 422);
            }
            $validator = Validator::make($request->all(), [
                'status' => 'in:cancelled',
                'notes' => 'nullable|string|max:500'
            ]);
        } elseif ($user->guide && $booking->guide_id === $user->guide->id) {
            // Guide can confirm or cancel their bookings
            $validator = Validator::make($request->all(), [
                'status' => 'in:confirmed,cancelled,completed',
                'notes' => 'nullable|string|max:500'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this booking'
            ], 403);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $booking->update($request->only(['status', 'notes']));
        $booking->load(['user', 'guide.user']);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'data' => $booking
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        $user = auth()->user();

        // Only admin or booking owner can delete
        if ($user->role !== 'admin' && $booking->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this booking'
            ], 403);
        }

        // Only allow deletion if booking is cancelled or pending
        if (!in_array($booking->status, ['pending', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete booking with current status'
            ], 422);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => 'Booking deleted successfully'
        ]);
    }

    /**
     * Get available time slots for a guide on a specific date
     */
    public function getAvailableSlots(Request $request, $guideId)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $guide = Guide::findOrFail($guideId);

        // Get existing bookings for the date
        $existingBookings = Booking::where('guide_id', $guideId)
            ->where('booking_date', $request->date)
            ->where('status', '!=', 'cancelled')
            ->select('start_time', 'end_time')
            ->get();

        // Define working hours (9 AM to 6 PM)
        $workingHours = [
            '09:00', '10:00', '11:00', '12:00', '13:00', 
            '14:00', '15:00', '16:00', '17:00'
        ];

        $availableSlots = [];
        
        foreach ($workingHours as $hour) {
            $isAvailable = true;
            
            foreach ($existingBookings as $booking) {
                $bookingStart = Carbon::createFromFormat('H:i:s', $booking->start_time)->format('H:i');
                $bookingEnd = Carbon::createFromFormat('H:i:s', $booking->end_time)->format('H:i');
                
                if ($hour >= $bookingStart && $hour < $bookingEnd) {
                    $isAvailable = false;
                    break;
                }
            }
            
            if ($isAvailable) {
                $availableSlots[] = $hour;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'guide' => $guide->load('user'),
                'date' => $request->date,
                'available_slots' => $availableSlots
            ]
        ]);
    }
}