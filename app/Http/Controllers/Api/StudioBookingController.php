<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudioBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StudioBookingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = StudioBooking::with(['requester', 'directManager']);

            // Filter based on user role
            if ($user->role === 'USER') {
                $query->where('requester_id', $user->id);
            } elseif ($user->role === 'DIRECT_MANAGER') {
                $query->where(function($q) use ($user) {
                    $q->where('requester_id', $user->id)
                      ->orWhere('direct_manager_id', $user->id);
                });
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Pagination with default 20 per page
            $perPage = $request->input('per_page', 20);
            $bookings = $query->orderBy('booking_date', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'pagination' => [
                    'total' => $bookings->total(),
                    'per_page' => $bookings->perPage(),
                    'current_page' => $bookings->currentPage(),
                    'last_page' => $bookings->lastPage(),
                    'from' => $bookings->firstItem(),
                    'to' => $bookings->lastItem(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_type' => 'required|in:photography,videography,both,podcast,interview,acting,product_photography,other',
                'custom_project_type' => 'required_if:project_type,other|nullable|string|max:255',
                'booking_date' => 'required|date',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'duration_hours' => 'nullable|numeric|min:0.5|max:24',
                'time_preference' => 'nullable|in:morning,evening,flexible',
                'equipment_needed' => 'nullable|array',
                'additional_services' => 'nullable|array',
                'additional_services.*' => 'in:special_lighting,makeup,decoration,catering',
                'crew_size' => 'nullable|integer|min:1',
                'client_name' => 'required|string|max:255',
                'client_phone' => 'required|string|max:20',
                'client_email' => 'nullable|email|max:255',
                'business_name' => 'nullable|string|max:255',
                'business_type' => 'nullable|string|max:255',
                'client_agreed' => 'required|boolean|accepted',
                'special_notes' => 'nullable|string|max:1000',
                'direct_manager_id' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            $booking = StudioBooking::create([
                'request_id' => 'STU-' . strtoupper(uniqid()),
                'title' => $request->title,
                'description' => $request->description,
                'project_type' => $request->project_type,
                'custom_project_type' => $request->custom_project_type,
                'requester_id' => Auth::id(),
                'direct_manager_id' => $request->direct_manager_id,
                'booking_date' => $request->booking_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'duration_hours' => $request->duration_hours,
                'time_preference' => $request->time_preference,
                'equipment_needed' => $request->equipment_needed ? json_encode($request->equipment_needed) : null,
                'additional_services' => $request->additional_services ? json_encode($request->additional_services) : null,
                'crew_size' => $request->crew_size,
                'client_name' => $request->client_name,
                'client_phone' => $request->client_phone,
                'client_email' => $request->client_email,
                'business_name' => $request->business_name,
                'business_type' => $request->business_type,
                'client_agreed' => $request->client_agreed,
                'agreement_date' => $request->client_agreed ? now() : null,
                'special_notes' => $request->special_notes,
                'status' => 'draft',
            ]);

            $booking->load(['requester', 'directManager']);

            return response()->json([
                'success' => true,
                'data' => $booking,
                'message' => 'Studio booking created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $booking = StudioBooking::with(['requester', 'directManager'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Studio booking not found']
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = StudioBooking::findOrFail($id);

            // Only draft bookings can be updated
            if ($booking->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft bookings can be updated']
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'project_type' => 'sometimes|required|in:photography,videography,both',
                'booking_date' => 'sometimes|required|date',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'equipment_needed' => 'nullable|array',
                'crew_size' => 'nullable|integer|min:1',
                'client_name' => 'nullable|string|max:255',
                'direct_manager_id' => 'sometimes|required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            $data = $request->only([
                'title', 'description', 'project_type', 'booking_date',
                'start_time', 'end_time', 'crew_size', 'client_name', 'direct_manager_id'
            ]);

            if ($request->has('equipment_needed')) {
                $data['equipment_needed'] = json_encode($request->equipment_needed);
            }

            $booking->update($data);
            $booking->load(['requester', 'directManager']);

            return response()->json([
                'success' => true,
                'data' => $booking,
                'message' => 'Studio booking updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function submit($id)
    {
        try {
            $booking = StudioBooking::findOrFail($id);

            if ($booking->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft bookings can be submitted']
                ], 422);
            }

            $booking->update(['status' => 'submitted']);

            return response()->json([
                'success' => true,
                'data' => $booking,
                'message' => 'Studio booking submitted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:dm_approved,dm_rejected,final_approved,final_rejected',
                'rejection_reason' => 'required_if:status,dm_rejected,final_rejected|nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            $booking = StudioBooking::findOrFail($id);

            $booking->update([
                'status' => $request->status,
                'rejection_reason' => $request->rejection_reason,
            ]);

            return response()->json([
                'success' => true,
                'data' => $booking,
                'message' => 'Status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = StudioBooking::findOrFail($id);

            // Only draft bookings can be deleted
            if ($booking->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft bookings can be deleted']
                ], 422);
            }

            $booking->delete();

            return response()->json([
                'success' => true,
                'message' => 'Studio booking deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get studio booking print data (JSON) - for frontend printing like visit details
     * No longer generates PDF - frontend will handle printing via window.print()
     */
    public function getPrintData($id)
    {
        try {
            $booking = StudioBooking::with(['requester', 'directManager'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $booking
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    // Keep old method for backward compatibility (can be removed later)
    public function downloadPdf($id)
    {
        // Redirect to the new print approach
        return $this->getPrintData($id);
    }

    public function getStats()
    {
        try {
            $user = Auth::user();
            $query = StudioBooking::query();

            if ($user->role === 'USER') {
                $query->where('requester_id', $user->id);
            } elseif ($user->role === 'DIRECT_MANAGER') {
                $query->where(function($q) use ($user) {
                    $q->where('requester_id', $user->id)
                      ->orWhere('direct_manager_id', $user->id);
                });
            }

            // Optimized single query with aggregation
            $result = $query->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft,
                SUM(CASE WHEN status = "submitted" THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status IN ("dm_approved", "final_approved") THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status IN ("dm_rejected", "final_rejected") THEN 1 ELSE 0 END) as rejected
            ')->first();

            $stats = [
                'total' => (int) $result->total,
                'draft' => (int) $result->draft,
                'submitted' => (int) $result->submitted,
                'approved' => (int) $result->approved,
                'rejected' => (int) $result->rejected,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }
}
