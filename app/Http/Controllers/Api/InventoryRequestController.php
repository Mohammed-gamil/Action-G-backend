<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryRequest;
use App\Models\InventoryRequestItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryRequestController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = InventoryRequest::with(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem']);

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
            $inventoryRequests = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequests->items(),
                'pagination' => [
                    'total' => $inventoryRequests->total(),
                    'per_page' => $inventoryRequests->perPage(),
                    'current_page' => $inventoryRequests->currentPage(),
                    'last_page' => $inventoryRequests->lastPage(),
                    'from' => $inventoryRequests->firstItem(),
                    'to' => $inventoryRequests->lastItem(),
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
                'direct_manager_id' => 'required|exists:users,id',
                // Employee Information
                'employee_name' => 'nullable|string|max:255',
                'employee_position' => 'nullable|string|max:255',
                'employee_phone' => 'nullable|string|max:20',
                // Exit Details
                'exit_purpose' => 'nullable|in:commercial_shoot,product_photography,event_coverage,client_project,training,maintenance,other',
                'custom_exit_purpose' => 'required_if:exit_purpose,other|nullable|string|max:255',
                'client_entity_name' => 'nullable|string|max:255',
                'shoot_location' => 'nullable|string',
                'exit_duration_from' => 'nullable|date',
                'exit_duration_to' => 'nullable|date|after_or_equal:exit_duration_from',
                'warehouse_manager_id' => 'nullable|exists:users,id',
                // Items
                'items' => 'required|array|min:1',
                'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'items.*.quantity_requested' => 'required|integer|min:1',
                'items.*.expected_return_date' => 'nullable|date',
                'items.*.serial_number' => 'nullable|string|max:255',
                'items.*.condition_before_exit' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            DB::beginTransaction();

            $inventoryRequest = InventoryRequest::create([
                'request_id' => 'INV-' . strtoupper(uniqid()),
                'title' => $request->title,
                'description' => $request->description,
                'requester_id' => Auth::id(),
                'direct_manager_id' => $request->direct_manager_id,
                'warehouse_manager_id' => $request->warehouse_manager_id,
                'employee_name' => $request->employee_name,
                'employee_position' => $request->employee_position,
                'employee_phone' => $request->employee_phone,
                'exit_purpose' => $request->exit_purpose,
                'custom_exit_purpose' => $request->custom_exit_purpose,
                'client_entity_name' => $request->client_entity_name,
                'shoot_location' => $request->shoot_location,
                'exit_duration_from' => $request->exit_duration_from,
                'exit_duration_to' => $request->exit_duration_to,
                'status' => 'draft',
            ]);

            // Create items and reserve quantities
            foreach ($request->items as $item) {
                $reqItem = InventoryRequestItem::create([
                    'inventory_request_id' => $inventoryRequest->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'expected_return_date' => $item['expected_return_date'] ?? null,
                    'serial_number' => $item['serial_number'] ?? null,
                    'condition_before_exit' => $item['condition_before_exit'] ?? null,
                ]);

                // Reserve stock for this draft request
                $inventoryItem = InventoryItem::where('id', $item['inventory_item_id'])->lockForUpdate()->first();
                if (!$inventoryItem) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => ['message' => 'Inventory item not found']
                    ], 404);
                }

                if (!$inventoryItem->reserve((int)$item['quantity_requested'], $inventoryRequest->id, Auth::id())) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => ['message' => "Insufficient available quantity for {$inventoryItem->name}"]
                    ], 422);
                }
            }

            DB::commit();

            $inventoryRequest->load(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem']);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest,
                'message' => 'Inventory request created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $inventoryRequest = InventoryRequest::with(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Inventory request not found']
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $inventoryRequest = InventoryRequest::findOrFail($id);

            // Only draft requests can be updated
            if ($inventoryRequest->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft requests can be updated']
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'direct_manager_id' => 'sometimes|required|exists:users,id',
                'items' => 'sometimes|required|array|min:1',
                'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
                'items.*.quantity_requested' => 'required|integer|min:1',
                'items.*.expected_return_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            DB::beginTransaction();

            $inventoryRequest->update($request->only(['title', 'description', 'direct_manager_id']));

            if ($request->has('items')) {
                // We will sync items instead of deleting all to preserve IDs and properly adjust reservations
                $existingItems = InventoryRequestItem::where('inventory_request_id', $inventoryRequest->id)->get()->keyBy('id');

                $incomingIds = [];

                foreach ($request->items as $item) {
                    // If id present, update
                    if (!empty($item['id']) && $existingItems->has($item['id'])) {
                        $reqItem = $existingItems->get($item['id']);

                        // If inventory_item_id/quantity changed, adjust reservations
                        if ($reqItem->inventory_item_id != $item['inventory_item_id'] || $reqItem->quantity_requested != $item['quantity_requested']) {
                            // Release previous reservation
                            $oldInventory = InventoryItem::where('id', $reqItem->inventory_item_id)->lockForUpdate()->first();
                            if ($oldInventory) {
                                $oldInventory->release((int)$reqItem->quantity_requested, $inventoryRequest->id, Auth::id());
                            }

                            // Reserve new
                            $newInventory = InventoryItem::where('id', $item['inventory_item_id'])->lockForUpdate()->first();
                            if (!$newInventory || !$newInventory->reserve((int)$item['quantity_requested'], $inventoryRequest->id, Auth::id())) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'error' => ['message' => 'Insufficient available quantity for requested item']
                                ], 422);
                            }
                        }

                        $reqItem->update([
                            'inventory_item_id' => $item['inventory_item_id'],
                            'quantity_requested' => $item['quantity_requested'],
                            'expected_return_date' => $item['expected_return_date'] ?? null,
                        ]);

                        $incomingIds[] = $reqItem->id;
                    } else {
                        // Create new item and reserve
                        $reqItem = InventoryRequestItem::create([
                            'inventory_request_id' => $inventoryRequest->id,
                            'inventory_item_id' => $item['inventory_item_id'],
                            'quantity_requested' => $item['quantity_requested'],
                            'expected_return_date' => $item['expected_return_date'] ?? null,
                        ]);

                        $inventoryItem = InventoryItem::where('id', $item['inventory_item_id'])->lockForUpdate()->first();
                        if (!$inventoryItem || !$inventoryItem->reserve((int)$item['quantity_requested'], $inventoryRequest->id, Auth::id())) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'error' => ['message' => 'Insufficient available quantity for requested item']
                            ], 422);
                        }

                        $incomingIds[] = $reqItem->id;
                    }
                }

                // Any existing items not in incomingIds should be removed and their reservations released
                $toRemove = $existingItems->filter(function ($v) use ($incomingIds) {
                    return !in_array($v->id, $incomingIds);
                });

                foreach ($toRemove as $rem) {
                    $oldInventory = InventoryItem::where('id', $rem->inventory_item_id)->lockForUpdate()->first();
                    if ($oldInventory) {
                        $oldInventory->release((int)$rem->quantity_requested, $inventoryRequest->id, Auth::id());
                    }
                    $rem->delete();
                }
            }

            DB::commit();

            $inventoryRequest->load(['requester', 'directManager', 'items.inventoryItem']);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest,
                'message' => 'Inventory request updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function submit($id)
    {
        try {
            $inventoryRequest = InventoryRequest::findOrFail($id);

            if ($inventoryRequest->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft requests can be submitted']
                ], 422);
            }

            $inventoryRequest->update(['status' => 'submitted']);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest,
                'message' => 'Inventory request submitted successfully'
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
                'status' => 'required|in:dm_approved,dm_rejected,final_approved,final_rejected,returned',
                'rejection_reason' => 'required_if:status,dm_rejected,final_rejected|nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            $inventoryRequest = InventoryRequest::with('items.inventoryItem')->findOrFail($id);

            DB::beginTransaction();

            // If status is changing to final_approved, allocate (consume) reserved inventory
            if ($request->status === 'final_approved' && $inventoryRequest->status !== 'final_approved') {
                foreach ($inventoryRequest->items as $item) {
                    $inventoryItem = InventoryItem::where('id', $item->inventory_item_id)->lockForUpdate()->first();

                    if (!$inventoryItem) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => ['message' => 'Inventory item not found']
                        ], 404);
                    }

                    // Try to allocate reserved quantity (this will check reserved and quantity)
                    if (!$inventoryItem->allocate((int)$item->quantity_requested, $inventoryRequest->id, Auth::id())) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => ['message' => "Unable to allocate {$item->quantity_requested} of {$inventoryItem->name}."]
                        ], 422);
                    }
                }
            }

            // If status is changing to a rejection, release any reserved stock
            if (in_array($request->status, ['dm_rejected', 'final_rejected']) && $inventoryRequest->status !== $request->status) {
                foreach ($inventoryRequest->items as $item) {
                    $inventoryItem = InventoryItem::where('id', $item->inventory_item_id)->lockForUpdate()->first();
                    if ($inventoryItem) {
                        $inventoryItem->release((int)$item->quantity_requested, $inventoryRequest->id, Auth::id());
                    }
                }
            }

            $inventoryRequest->update([
                'status' => $request->status,
                'rejection_reason' => $request->rejection_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest,
                'message' => 'Status updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $inventoryRequest = InventoryRequest::findOrFail($id);

            // Only draft requests can be deleted
            if ($inventoryRequest->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only draft requests can be deleted']
                ], 422);
            }

            $inventoryRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inventory request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    public function getStats()
    {
        try {
            $user = Auth::user();
            $query = InventoryRequest::query();

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
                SUM(CASE WHEN status IN ("dm_rejected", "final_rejected") THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = "returned" THEN 1 ELSE 0 END) as returned
            ')->first();

            $stats = [
                'total' => (int) $result->total,
                'draft' => (int) $result->draft,
                'submitted' => (int) $result->submitted,
                'approved' => (int) $result->approved,
                'rejected' => (int) $result->rejected,
                'returned' => (int) $result->returned,
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

    public function recordReturn(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'return_date' => 'required|date',
                'return_supervisor_name' => 'required|string|max:255',
                'return_supervisor_phone' => 'required|string|max:20',
                'equipment_condition_on_return' => 'nullable|string',
                'supervisor_notes' => 'nullable|string',
                'returned_by_employee' => 'nullable|string|max:255',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:tbl_inventory_request_items,id',
                'items.*.quantity_returned' => 'required|integer|min:0',
                'items.*.condition_after_return' => 'nullable|string',
                'items.*.return_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => $validator->errors()->first()]
                ], 422);
            }

            $inventoryRequest = InventoryRequest::findOrFail($id);

            // Only final_approved requests can have returns recorded
            if ($inventoryRequest->status !== 'final_approved') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Only approved requests can have returns recorded']
                ], 422);
            }

            DB::beginTransaction();

            // Update main request
            $inventoryRequest->update([
                'status' => 'returned',
                'return_date' => $request->return_date,
                'return_supervisor_name' => $request->return_supervisor_name,
                'return_supervisor_phone' => $request->return_supervisor_phone,
                'equipment_condition_on_return' => $request->equipment_condition_on_return,
                'supervisor_notes' => $request->supervisor_notes,
                'returned_by_employee' => $request->returned_by_employee,
            ]);

            // Update items with return information AND restore inventory quantities
            foreach ($request->items as $item) {
                $requestItem = InventoryRequestItem::with('inventoryItem')->findOrFail($item['id']);
                
                // Update return details
                $requestItem->update([
                    'quantity_returned' => $item['quantity_returned'],
                    'condition_after_return' => $item['condition_after_return'] ?? null,
                    'return_notes' => $item['return_notes'] ?? null,
                ]);

                // Add returned quantity back to inventory stock
                if ($item['quantity_returned'] > 0 && $requestItem->inventoryItem) {
                    $inventoryItem = InventoryItem::where('id', $requestItem->inventory_item_id)->lockForUpdate()->first();
                    if (!$inventoryItem) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => ['message' => 'Inventory item not found']
                        ], 404);
                    }

                    // Prevent over-return (cannot return more than was requested)
                    if ($item['quantity_returned'] > $requestItem->quantity_requested) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => ['message' => "Returned quantity for item {$inventoryItem->name} exceeds requested quantity"]
                        ], 422);
                    }

                    $inventoryItem->addStock((int)$item['quantity_returned'], Auth::id(), "Returned from request {$inventoryRequest->request_id}. Condition: " . ($item['condition_after_return'] ?? 'N/A'));
                }
            }

            DB::commit();

            $inventoryRequest->load(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem']);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest,
                'message' => 'Return recorded successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get inventory request print data (JSON) - for frontend printing like visit details
     * No longer generates PDF - frontend will handle printing via window.print()
     */
    public function getPrintData($id)
    {
        try {
            $inventoryRequest = InventoryRequest::with(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }

    /**
     * Get return receipt print data (JSON) - for frontend printing
     * No longer generates PDF - frontend will handle printing via window.print()
     */
    public function getReturnReceiptPrintData($id)
    {
        try {
            $inventoryRequest = InventoryRequest::with(['requester', 'directManager', 'warehouseManager', 'items.inventoryItem'])->findOrFail($id);

            // Only returned requests can have return receipts
            if ($inventoryRequest->status !== 'returned') {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Return receipt is only available for returned requests']
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $inventoryRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['message' => $e->getMessage()]
            ], 500);
        }
    }
    
    // Keep old methods for backward compatibility (can be removed later)
    public function downloadPdf($id)
    {
        // Redirect to the new print approach
        return $this->getPrintData($id);
    }

    public function downloadReturnReceipt($id)
    {
        // Redirect to the new print approach
        return $this->getReturnReceiptPrintData($id);
    }
}
