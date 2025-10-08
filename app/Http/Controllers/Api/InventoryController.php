<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    /**
     * Display a listing of inventory items
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $category = $request->get('category');
        $search = $request->get('search');
        $inStockOnly = $request->get('in_stock_only', false);
        $activeOnly = $request->get('active_only', true);

        $query = InventoryItem::with(['addedBy', 'updatedBy'])
            ->when($category, fn($q) => $q->byCategory($category))
            ->when($search, fn($q) => $q->search($search))
            ->when($inStockOnly, fn($q) => $q->inStock())
            ->when($activeOnly, fn($q) => $q->active())
            ->orderBy('name', 'asc');

        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'pagination' => [
                    'page' => $items->currentPage(),
                    'limit' => $items->perPage(),
                    'total' => $items->total(),
                    'totalPages' => $items->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Store a newly created inventory item (Manager/Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->canManageInventory()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only managers and admins can add inventory items'
                ]
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'quantity' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'condition' => 'nullable|in:good,fair,needs_maintenance',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date|after:last_maintenance_date',
            'notes' => 'nullable|string',
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

            $item = InventoryItem::create([
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'quantity' => $request->quantity,
                'unit' => $request->unit,
                'unit_cost' => $request->unit_cost,
                'location' => $request->location,
                'condition' => $request->condition ?? 'good',
                'last_maintenance_date' => $request->last_maintenance_date,
                'next_maintenance_date' => $request->next_maintenance_date,
                'notes' => $request->notes,
                'added_by' => $user->id,
            ]);

            // Log initial stock transaction
            if ($request->quantity > 0) {
                InventoryTransaction::create([
                    'inventory_item_id' => $item->id,
                    'type' => 'IN',
                    'quantity' => $request->quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $request->quantity,
                    'user_id' => $user->id,
                    'notes' => 'Initial stock',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory item created successfully',
                'data' => $item->load(['addedBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to create inventory item',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Display the specified inventory item
     */
    public function show(string $id): JsonResponse
    {
        $item = InventoryItem::with(['addedBy', 'updatedBy', 'transactions.user'])
            ->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Inventory item not found'
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    /**
     * Update the specified inventory item (Manager/Admin only)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->canManageInventory()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only managers and admins can update inventory items'
                ]
            ], 403);
        }

        $item = InventoryItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Inventory item not found'
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:100',
            'unit' => 'sometimes|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'condition' => 'nullable|in:good,fair,needs_maintenance',
            'last_maintenance_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
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
            $item->update(array_merge(
                $request->only([
                    'name', 'description', 'category', 'unit', 'unit_cost',
                    'location', 'condition', 'last_maintenance_date',
                    'next_maintenance_date', 'is_active', 'notes'
                ]),
                ['updated_by' => $user->id]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Inventory item updated successfully',
                'data' => $item->fresh(['addedBy', 'updatedBy'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to update inventory item',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Adjust inventory quantity (Manager/Admin only)
     */
    public function adjustQuantity(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->canManageInventory()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only managers and admins can adjust inventory quantities'
                ]
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
            'type' => 'required|in:add,remove,set',
            'notes' => 'nullable|string|max:1000',
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

        $item = InventoryItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Inventory item not found'
                ]
            ], 404);
        }

        try {
            DB::beginTransaction();

            $quantityBefore = $item->quantity;
            $quantityChange = 0;

            switch ($request->type) {
                case 'add':
                    $item->quantity += $request->quantity;
                    $quantityChange = $request->quantity;
                    $transactionType = 'IN';
                    break;
                case 'remove':
                    if ($item->available_quantity < $request->quantity) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 422,
                                'message' => 'Insufficient available quantity. Available: ' . $item->available_quantity
                            ]
                        ], 422);
                    }
                    $item->quantity -= $request->quantity;
                    $quantityChange = -$request->quantity;
                    $transactionType = 'OUT';
                    break;
                case 'set':
                    if ($request->quantity < $item->reserved_quantity) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'error' => [
                                'code' => 422,
                                'message' => 'Cannot set quantity below reserved amount: ' . $item->reserved_quantity
                            ]
                        ], 422);
                    }
                    $quantityChange = $request->quantity - $item->quantity;
                    $item->quantity = $request->quantity;
                    $transactionType = 'ADJUSTMENT';
                    break;
            }

            $item->updated_by = $user->id;
            $item->save();

            // Log transaction
            InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'type' => $transactionType,
                'quantity' => $quantityChange,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $item->quantity,
                'user_id' => $user->id,
                'notes' => $request->notes ?? "Quantity adjustment: {$request->type}",
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory quantity adjusted successfully',
                'data' => $item->fresh(['addedBy', 'updatedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to adjust inventory quantity',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Get inventory transactions history
     */
    public function transactions(Request $request, string $id): JsonResponse
    {
        $perPage = $request->get('per_page', 50);

        $item = InventoryItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Inventory item not found'
                ]
            ], 404);
        }

        $transactions = $item->transactions()
            ->with(['user', 'request'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'pagination' => [
                    'page' => $transactions->currentPage(),
                    'limit' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'totalPages' => $transactions->lastPage()
                ]
            ]
        ]);
    }

    /**
     * Get available categories
     */
    public function categories(): JsonResponse
    {
        $categories = InventoryItem::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Soft delete inventory item (Admin only)
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only administrators can delete inventory items'
                ]
            ], 403);
        }

        $item = InventoryItem::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Inventory item not found'
                ]
            ], 404);
        }

        if ($item->reserved_quantity > 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 422,
                    'message' => 'Cannot delete item with reserved quantity. Please release all reservations first.'
                ]
            ], 422);
        }

        try {
            $item->delete();

            return response()->json([
                'success' => true,
                'message' => 'Inventory item deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to delete inventory item',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
