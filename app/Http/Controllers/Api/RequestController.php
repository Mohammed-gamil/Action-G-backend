<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\User;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in routes
    }

    /**
     * Display a listing of requests
     */
    public function index(HttpRequest $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->get('per_page', 15);
        $type = $request->get('type'); // purchase, project
        $state = $request->get('state');
        $search = $request->get('search');

    $query = Request::with(['requester', 'currentApprover', 'directManager', 'items', 'approvals.approver', 'quotes', 'selectedQuote'])
            ->when($type, function ($q, $type) {
                return $q->byType($type);
            })
            ->when($state, function ($q, $state) {
                return $q->byState($state);
            })
            ->when($search, function ($q, $search) {
                return $q->where(function ($subQ) use ($search) {
                    $subQ->where('title', 'like', "%{$search}%")
                         ->orWhere('request_id', 'like', "%{$search}%")
                         ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc');

        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests->items(),
            'meta' => [
                'pagination' => [
                    'page' => $requests->currentPage(),
                    'limit' => $requests->perPage(),
                    'total' => $requests->total(),
                    'totalPages' => $requests->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Store a newly created request
     */
    public function store(HttpRequest $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:purchase,project',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'location' => 'nullable|string|max:255',
            'desired_cost' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'needed_by_date' => 'required|date|after:today',
            'start_time' => 'required_if:type,project|date',
            'end_time' => 'required_if:type,project|date|after:start_time',
            'submit_immediately' => 'boolean', // إضافة parameter للتحكم

            // Purchase request fields
            'items' => 'required_if:type,purchase|array|min:1',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.vendor_hint' => 'nullable|string|max:255',

            // Project request fields
            'client_name' => 'required_if:type,project|string|max:255',
            'project_description' => 'required_if:type,project|string',
            'total_cost' => 'required_if:type,project|numeric|min:0',
            'total_benefit' => 'required_if:type,project|numeric|min:0',
            'total_price' => 'required_if:type,project|numeric|min:0',
            
            // Inventory items for project requests
            'inventory_items' => 'nullable|array',
            'inventory_items.*.inventory_item_id' => 'required_with:inventory_items|exists:inventory_items,id',
            'inventory_items.*.quantity_requested' => 'required_with:inventory_items|integer|min:1',
            'inventory_items.*.expected_return_date' => 'nullable|date|after:start_time',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::user();

            // DM stage: optionally assign a specific direct manager if provided
            $currentApprover = null;

            $requestData = [
                'requester_id' => $user->id,
                'title' => $request->title,
                'description' => $request->description,
                'type' => $request->type,
                'category' => $request->category,
                'location' => $request->get('location'),
                'desired_cost' => $request->desired_cost,
                'currency' => $request->currency,
                'needed_by_date' => $request->needed_by_date,
                'start_time' => $request->get('start_time'),
                'end_time' => $request->get('end_time'),
                'state' => $request->get('submit_immediately', true) ? 'SUBMITTED' : 'DRAFT',
                'current_approver_id' => $currentApprover?->id,
                'direct_manager_id' => $request->get('direct_manager_id'),
            ];

            // Add project-specific fields
            if ($request->type === 'project') {
                $requestData = array_merge($requestData, [
                    'client_name' => $request->client_name,
                    'project_description' => $request->project_description,
                    'total_cost' => $request->total_cost,
                    'total_benefit' => $request->total_benefit,
                    'total_price' => $request->total_price,
                ]);
            }

            $newRequest = Request::create($requestData);

            // Add items for both purchase and project requests (if provided)
            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $itemData) {
                    RequestItem::create([
                        'request_id' => $newRequest->id,
                        'name' => $itemData['name'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'vendor_hint' => $itemData['vendor_hint'] ?? null,
                    ]);
                }
            }

            // Attach inventory items for project requests
            if ($request->type === 'project' && $request->has('inventory_items') && is_array($request->inventory_items)) {
                foreach ($request->inventory_items as $invItem) {
                    $inventoryItem = \App\Models\InventoryItem::find($invItem['inventory_item_id']);
                    
                    if (!$inventoryItem) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 404,
                                'message' => 'Inventory item not found: ' . $invItem['inventory_item_id']
                            ]
                        ], 404);
                    }

                    // Check availability
                    if (!$inventoryItem->canReserve($invItem['quantity_requested'])) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 422,
                                'message' => "Insufficient inventory for {$inventoryItem->name}. Available: {$inventoryItem->available_quantity}"
                            ]
                        ], 422);
                    }

                    // Create request inventory item (will be reserved when request is approved)
                    \App\Models\RequestInventoryItem::create([
                        'request_id' => $newRequest->id,
                        'inventory_item_id' => $invItem['inventory_item_id'],
                        'quantity_requested' => $invItem['quantity_requested'],
                        'expected_return_date' => $invItem['expected_return_date'] ?? null,
                        'status' => 'PENDING',
                    ]);
                }
            }

            DB::commit();

            $newRequest->load(['requester', 'currentApprover', 'directManager', 'items', 'approvals', 'inventoryItems.inventoryItem']);

            return response()->json([
                'success' => true,
                'message' => 'Request created successfully',
                'data' => $newRequest
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // If duplicate key still occurs for some reason, retry once by regenerating request_id
            if (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), 'requests_request_id_unique')) {
                try {
                    DB::beginTransaction();
                    $requestData['request_id'] = Request::nextRequestId($request->type);
                    $newRequest = Request::create($requestData);
                    if ($request->has('items') && is_array($request->items)) {
                        foreach ($request->items as $itemData) {
                            RequestItem::create([
                                'request_id' => $newRequest->id,
                                'name' => $itemData['name'],
                                'quantity' => $itemData['quantity'],
                                'unit_price' => $itemData['unit_price'],
                                'vendor_hint' => $itemData['vendor_hint'] ?? null,
                            ]);
                        }
                    }
                    DB::commit();
                    $newRequest->load(['requester', 'currentApprover', 'directManager', 'items', 'approvals']);
                    return response()->json([
                        'success' => true,
                        'message' => 'Request created successfully',
                        'data' => $newRequest
                    ], 201);
                } catch (\Exception $e2) {
                    DB::rollBack();
                }
            }
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to create request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Display the specified request
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

    $query = Request::with(['requester', 'currentApprover', 'directManager', 'items', 'quotes', 'selectedQuote', 'approvals.approver', 'inventoryItems.inventoryItem']);

        // Support lookup by numeric id or by request_id like PR-2025-002
        if (ctype_digit($id)) {
            $query->where('id', $id);
        } else {
            $query->where('request_id', $id);
        }

        $request = $query
            ->when(!$user->isAdmin(), function ($q) use ($user) {
                return $q->where(function ($subQ) use ($user) {
                    $subQ->where('requester_id', $user->id)
                         ->orWhere('current_approver_id', $user->id)
                         // Allow pooled approvers to view at their stages: all DMs see SUBMITTED purchases
                         ->orWhere(function ($inner) use ($user) {
                             if ($user->role === 'DIRECT_MANAGER') {
                                 $inner->where('state', 'SUBMITTED')->where('type', 'purchase');
                             } elseif ($user->role === 'ACCOUNTANT') {
                                 $inner->where('state', 'DM_APPROVED');
                             }
                         });
                });
            })
            ->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $request
        ]);
    }

    /**
     * Submit request for approval
     */
    public function submit(string $id): JsonResponse
    {
        $user = Auth::user();

        $query = Request::where('id', $id)->where('state', 'DRAFT');

        // Allow requester to submit their own draft, or a DIRECT_MANAGER/ADMIN to submit on behalf
        $request = $query->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found or cannot be submitted'
                ]
            ], 404);
        }

        if ($request->requester_id !== $user->id && !in_array($user->role, ['DIRECT_MANAGER', 'ADMIN'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only the requester, a direct manager, or an admin can submit this request'
                ]
            ], 403);
        }

    // DM stage is pool-based; no specific approver assignment
    $nextApprover = null;

        $request->update([
            'state' => 'SUBMITTED',
            'current_approver_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request submitted for approval',
            'data' => $request->load(['requester', 'currentApprover'])
        ]);
    }

    /**
     * Upload and attach a vendor quote (accountant or admin only)
     */
    public function uploadQuote(HttpRequest $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!in_array($user->role, ['ACCOUNTANT', 'ADMIN'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only accountants or admins can upload quotes'
                ]
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'vendor_name' => 'required|string|max:255',
            'quote_total' => 'required|numeric|min:0.01',
            // Only remote URL is accepted
            'file_url' => 'required|url|max:2048',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all(),
                ]
            ], 422);
        }

        $reqQuery = Request::query();
        if (ctype_digit($id)) {
            $reqQuery->where('id', $id);
        } else {
            $reqQuery->where('request_id', $id);
        }
        $pr = $reqQuery->first();

        if (!$pr) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            \App\Models\RequestQuote::create([
                'request_id' => $pr->id,
                'vendor_name' => $request->input('vendor_name'),
                'quote_total' => $request->input('quote_total'),
                'file_path' => $request->input('file_url'),
                'notes' => $request->input('notes'),
                'uploaded_at' => now(),
            ]);

            DB::commit();

            $pr->load(['quotes', 'selectedQuote']);

            return response()->json([
                'success' => true,
                'message' => 'Quote uploaded successfully',
                'data' => $pr,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to upload quote',
                    'details' => [$e->getMessage()],
                ]
            ], 500);
        }
    }

    /**
     * Update the specified request (only in DRAFT and by owner)
     */
    public function update(HttpRequest $request, string $id): JsonResponse
    {
        $user = Auth::user();

        $pr = Request::with(['items'])
            ->where('id', $id)
            ->where('requester_id', $user->id)
            ->where('state', 'DRAFT')
            ->first();

        if (!$pr) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found or cannot be edited'
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'desired_cost' => 'sometimes|numeric|min:0',
            'needed_by_date' => 'sometimes|date|after:today',
            'items' => 'nullable|array|min:1',
            'items.*.name' => 'required_with:items|string|max:255',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.vendor_hint' => 'nullable|string|max:255',
            // Project-specific optional fields
            'client_name' => 'sometimes|string|max:255',
            'project_description' => 'sometimes|string',
            'total_cost' => 'sometimes|numeric|min:0',
            'total_benefit' => 'sometimes|numeric|min:0',
            'total_price' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'title', 'description', 'desired_cost', 'needed_by_date',
                'client_name', 'project_description', 'total_cost', 'total_benefit', 'total_price'
            ]);

            $pr->update($updateData);

            if ($request->has('items')) {
                // Replace items wholesale if provided
                $pr->items()->delete();
                foreach ($request->items as $itemData) {
                    RequestItem::create([
                        'request_id' => $pr->id,
                        'name' => $itemData['name'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'vendor_hint' => $itemData['vendor_hint'] ?? null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully',
                'data' => $pr->fresh(['requester', 'items'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to update request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified request (only in DRAFT and by owner)
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        $pr = Request::with(['items'])
            ->where('id', $id)
            ->where('requester_id', $user->id)
            ->where('state', 'DRAFT')
            ->first();

        if (!$pr) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found or cannot be deleted'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();
            $pr->items()->delete();
            $pr->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to delete request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Admin: delete any request (purchase or project) regardless of state
     */
    public function adminDestroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only administrators can delete requests'
                ]
            ], 403);
        }

        $pr = Request::with(['items', 'quotes', 'approvals'])
            ->where('id', $id)
            ->first();

        if (!$pr) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();
            $pr->items()->delete();
            $pr->quotes()->delete();
            $pr->approvals()->delete();
            $pr->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Request deleted successfully by admin'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to delete request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Get requests that need approval by current user
     */
    public function pendingApprovals(HttpRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->canApproveRequests()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Access denied'
                ]
            ], 403);
        }

        $perPage = $request->get('per_page', 15);

        $query = Request::with(['requester', 'items', 'quotes', 'selectedQuote', 'approvals.approver'])
            ->forApprover($user->id, $user->role)
            ->orderBy('created_at', 'asc');

        $requests = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $requests->items(),
            'meta' => [
                'pagination' => [
                    'page' => $requests->currentPage(),
                    'limit' => $requests->perPage(),
                    'total' => $requests->total(),
                    'totalPages' => $requests->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Get requests for a specific user (Admin only)
     */
    public function getUserRequests(string $userId): JsonResponse
    {
        $currentUser = Auth::user();

        // Only admin or the user themselves can view user requests
        if (!$currentUser->isAdmin() && $currentUser->id != $userId) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Access denied. You can only view your own requests.'
                ]
            ], 403);
        }

        $requests = Request::with(['requester', 'currentApprover', 'items', 'approvals.approver'])
            ->where('requester_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
            'meta' => [
                'total' => $requests->count(),
                'user_id' => $userId
            ]
        ]);
    }

    /**
     * Get inventory items attached to a request
     */
    public function getInventoryItems(string $id): JsonResponse
    {
        $request = Request::with(['inventoryItems.inventoryItem'])
            ->where('id', $id)
            ->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $request->inventoryItems
        ]);
    }

    /**
     * Attach inventory items to a request (only in DRAFT state)
     */
    public function attachInventoryItems(HttpRequest $httpRequest, string $id): JsonResponse
    {
        $user = Auth::user();

        $request = Request::where('id', $id)
            ->where('requester_id', $user->id)
            ->where('state', 'DRAFT')
            ->where('type', 'project')
            ->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Project request not found or cannot be modified'
                ]
            ], 404);
        }

        $validator = Validator::make($httpRequest->all(), [
            'inventory_items' => 'required|array|min:1',
            'inventory_items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'inventory_items.*.quantity_requested' => 'required|integer|min:1',
            'inventory_items.*.expected_return_date' => 'nullable|date|after:' . $request->start_time,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Validation failed',
                    'details' => $validator->errors()->all()
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Remove existing inventory items
            $request->inventoryItems()->delete();

            // Add new inventory items
            foreach ($httpRequest->inventory_items as $invItem) {
                $inventoryItem = \App\Models\InventoryItem::find($invItem['inventory_item_id']);
                
                if (!$inventoryItem) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 404,
                            'message' => 'Inventory item not found'
                        ]
                    ], 404);
                }

                // Check availability
                if (!$inventoryItem->canReserve($invItem['quantity_requested'])) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 422,
                            'message' => "Insufficient inventory for {$inventoryItem->name}. Available: {$inventoryItem->available_quantity}"
                        ]
                    ], 422);
                }

                \App\Models\RequestInventoryItem::create([
                    'request_id' => $request->id,
                    'inventory_item_id' => $invItem['inventory_item_id'],
                    'quantity_requested' => $invItem['quantity_requested'],
                    'expected_return_date' => $invItem['expected_return_date'] ?? null,
                    'status' => 'PENDING',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory items attached successfully',
                'data' => $request->fresh(['inventoryItems.inventoryItem'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to attach inventory items',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Find the next approver based on user and request state
     */
    private function findNextApprover(User $user, string $state): ?User
    {
        switch ($state) {
            case 'SUBMITTED':
                return User::where('role', 'DIRECT_MANAGER')
                    ->where('status', 'active')
                    ->inRandomOrder()
                    ->first();

            default:
                return null;
        }
    }
}