<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Request;
use App\Models\Approval;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApprovalController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in routes
    }

    /**
     * Approve a request
     */
    public function approve(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($httpRequest->all(), [
            'comment' => 'nullable|string|max:1000',
            'payout_channel' => 'nullable|in:WALLET,COMPANY,COURIER'
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

        $requestQuery = Request::with(['requester', 'currentApprover', 'approvals']);
        if (ctype_digit($requestId)) {
            $requestQuery->where('id', $requestId);
        } else {
            $requestQuery->where('request_id', $requestId);
        }
        $request = $requestQuery->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        if (!$request->canBeApprovedBy($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'You are not authorized to approve this request'
                ]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Lock the request row to guard against concurrent actions
            $locked = Request::where('id', $request->id)->lockForUpdate()->first();
            if (!$locked) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 404, 'message' => 'Request not found' ]
                ], 404);
            }

            // Verify expected state based on role and request type
            $expectedState = match($user->role) {
                'DIRECT_MANAGER' => 'SUBMITTED',
                'FINAL_MANAGER' => ($locked->type === 'project' ? 'SUBMITTED' : 'DM_APPROVED'),
                'ACCOUNTANT' => null,
                default => null
            };
            if ($expectedState && $locked->state !== $expectedState) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 409, 'message' => 'Request already processed by another approver' ]
                ], 409);
            }

            // Lock the request row to ensure first-actor-wins in pooled stages
            $locked = Request::where('id', $request->id)->lockForUpdate()->first();
            if (!$locked) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 404, 'message' => 'Request not found' ]
                ], 404);
            }

            // Verify state still allows this user to approve (handles race conditions)
            $expectedState = match($user->role) {
                'DIRECT_MANAGER' => 'SUBMITTED',
                'FINAL_MANAGER' => ($locked->type === 'project' ? 'SUBMITTED' : 'DM_APPROVED'),
                'ACCOUNTANT' => null,
                default => null
            };
            if ($expectedState && $locked->state !== $expectedState) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 409, 'message' => 'Request already processed by another approver' ]
                ], 409);
            }

            // Create approval record
            $approval = Approval::create([
                'request_id' => $request->id,
                'stage' => $this->getApprovalStage($user->role),
                'approver_id' => $user->id,
                'decision' => 'APPROVED',
                'comment' => $httpRequest->comment,
                'decided_at' => now()
            ]);

            // If Final Manager is approving a purchase (DM_APPROVED), ensure a quote is selected
            if ($user->role === 'FINAL_MANAGER' && $locked->type === 'purchase' && $locked->state === 'DM_APPROVED') {
                if (!$locked->selected_quote_id) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => [ 'code' => 422, 'message' => 'Please select a quote before final approval' ]
                    ], 422);
                }
            }

            // Update request state
            $newState = $locked->getNextApprovalState('APPROVED');
            $updateData = [
                'state' => $newState,
                // DM and Final Manager stages are pool-based; keep approver null
                'current_approver_id' => null
            ];

            // Final Manager approval doesn't set payout channel (that's for accountant processing)
            // Payout channel will be set when accountant processes the payment

            // If project FM approval moved to PROCESSING per new flow

            $locked->update($updateData);

            // Reserve inventory items when project is approved (moves to PROCESSING)
            if ($locked->type === 'project' && $newState === 'PROCESSING') {
                $inventoryItems = $locked->inventoryItems()->with('inventoryItem')->get();
                foreach ($inventoryItems as $reqInvItem) {
                    $invItem = $reqInvItem->inventoryItem;
                    if ($invItem && $invItem->canReserve($reqInvItem->quantity_requested)) {
                        $invItem->reserve($reqInvItem->quantity_requested, $locked->id, $user->id);
                        $reqInvItem->status = 'RESERVED';
                        $reqInvItem->save();
                    } else {
                        // Log warning but don't fail the approval
                        \Log::warning("Could not reserve inventory item {$invItem->name} for request {$locked->id}");
                    }
                }
            }

            DB::commit();

            // Send notifications
            NotificationService::requestApproved($locked, $user, $this->getApprovalStage($user->role));

            return response()->json([
                'success' => true,
                'message' => 'Request approved successfully',
                'data' => [
                    'request' => $locked->fresh(['requester', 'currentApprover', 'directManager', 'approvals.approver', 'quotes', 'selectedQuote']),
                    'approval' => $approval->load('approver')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to approve request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Mark project as DONE (requester confirms project ended)
     */
    public function markProjectDone(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();

        $req = Request::where(function ($q) use ($requestId) {
                if (ctype_digit($requestId)) $q->where('id', $requestId); else $q->where('request_id', $requestId);
            })
            ->where('type', 'project')
            ->first();

        if (!$req) {
            return response()->json(['success' => false, 'error' => ['code' => 404, 'message' => 'Request not found']], 404);
        }
        if ($req->requester_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 403, 'message' => 'Only requester or admin can mark as done']], 403);
        }
        if ($req->state !== 'PROCESSING') {
            return response()->json(['success' => false, 'error' => ['code' => 409, 'message' => 'Project not in processing state']], 409);
        }

        $req->update(['state' => 'DONE']);

        // Send notifications
        NotificationService::projectMarkedDone($req);

        return response()->json(['success' => true, 'message' => 'Project marked as done', 'data' => $req->fresh(['requester', 'directManager'])]);
    }

    /**
     * Confirm client payment (accountant)
     */
    public function confirmClientPaid(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();
        if ($user->role !== 'ACCOUNTANT' && !$user->isAdmin()) {
            return response()->json(['success' => false, 'error' => ['code' => 403, 'message' => 'Only accountant or admin can confirm payment']], 403);
        }

        $req = Request::where(function ($q) use ($requestId) {
                if (ctype_digit($requestId)) $q->where('id', $requestId); else $q->where('request_id', $requestId);
            })
            ->where('type', 'project')
            ->first();

        if (!$req) {
            return response()->json(['success' => false, 'error' => ['code' => 404, 'message' => 'Request not found']], 404);
        }
        if ($req->state !== 'DONE') {
            return response()->json(['success' => false, 'error' => ['code' => 409, 'message' => 'Project not in done state']], 409);
        }

        $validator = Validator::make($httpRequest->all(), [
            'payout_reference' => 'nullable|string|max:255'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => ['code' => 422, 'message' => 'Validation failed', 'details' => $validator->errors()->all()]], 422);
        }

        $req->update([
            'state' => 'PAID',
            'payout_reference' => $httpRequest->get('payout_reference')
        ]);

        // Send notifications
        NotificationService::clientPaymentConfirmed($req);

        return response()->json(['success' => true, 'message' => 'Payment confirmed', 'data' => $req->fresh(['requester', 'directManager'])]);
    }

    /**
     * Reject a request
     */
    public function reject(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();

        // Accountants are not allowed to reject; they must approve after selecting a quote
        if ($user->role === 'ACCOUNTANT') {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Accountants cannot reject requests. Please select a quote and approve.'
                ]
            ], 403);
        }

        $validator = Validator::make($httpRequest->all(), [
            'comment' => 'required|string|max:1000'
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

        $requestQuery = Request::with(['requester', 'currentApprover', 'approvals']);
        if (ctype_digit($requestId)) {
            $requestQuery->where('id', $requestId);
        } else {
            $requestQuery->where('request_id', $requestId);
        }
        $request = $requestQuery->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found'
                ]
            ], 404);
        }

        if (!$request->canBeRejectedBy($user)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'You are not authorized to reject this request'
                ]
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Lock the request row
            $locked = Request::where('id', $request->id)->lockForUpdate()->first();
            if (!$locked) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 404, 'message' => 'Request not found' ]
                ], 404);
            }

            // Verify expected state for rejecting user
            $expectedState = match($user->role) {
                'DIRECT_MANAGER' => 'SUBMITTED',
                'FINAL_MANAGER' => ($locked->type === 'project' ? 'SUBMITTED' : 'DM_APPROVED'),
                'ACCOUNTANT' => 'DM_APPROVED',
                default => null
            };

            if ($expectedState && $locked->state !== $expectedState) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => [ 'code' => 409, 'message' => 'Request already processed by another approver' ]
                ], 409);
            }

            // No final manager stage anymore

            // Create approval record
            $approval = Approval::create([
                'request_id' => $locked->id,
                'stage' => $this->getApprovalStage($user->role),
                'approver_id' => $user->id,
                'decision' => 'REJECTED',
                'comment' => $httpRequest->comment,
                'decided_at' => now()
            ]);

            // Update request state to rejected stage
            $newState = $locked->getNextApprovalState('REJECTED');

            $locked->update([
                'state' => $newState,
                'current_approver_id' => null
            ]);

            DB::commit();

            // Send notifications
            NotificationService::requestRejected($locked, $user, $httpRequest->comment);

            return response()->json([
                'success' => true,
                'message' => 'Request rejected successfully',
                'data' => [
                    'request' => $locked->fresh(['requester', 'currentApprover', 'directManager', 'approvals.approver', 'quotes', 'selectedQuote']),
                    'approval' => $approval->load('approver')
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 500,
                    'message' => 'Failed to reject request',
                    'details' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    /**
     * Transfer funds (Admin only)
     */
    public function transferFunds(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 403,
                    'message' => 'Only administrators can transfer funds'
                ]
            ], 403);
        }

        $validator = Validator::make($httpRequest->all(), [
            'payout_reference' => 'required|string|max:255'
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

        $requestQuery = Request::query();
        if (ctype_digit($requestId)) {
            $requestQuery->where('id', $requestId);
        } else {
            $requestQuery->where('request_id', $requestId);
        }
        $request = $requestQuery->where('state', 'FINAL_APPROVED')->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found or not ready for funds transfer'
                ]
            ], 404);
        }

        $request->update([
            'state' => 'FUNDS_TRANSFERRED',
            'payout_reference' => $httpRequest->payout_reference,
            'funds_transferred_at' => now()
        ]);

        // Send notifications
        NotificationService::fundsTransferred($request, $httpRequest->payout_reference);

        return response()->json([
            'success' => true,
            'message' => 'Funds transferred successfully',
            'data' => $request->fresh(['requester', 'directManager', 'approvals.approver', 'quotes', 'selectedQuote'])
        ]);
    }

    /**
     * Get approval history for a request
     */
    public function history(string $requestId): JsonResponse
    {
        $user = Auth::user();

        $requestQuery = Request::query();
        if (ctype_digit($requestId)) {
            $requestQuery->where('id', $requestId);
        } else {
            $requestQuery->where('request_id', $requestId);
        }
        $request = $requestQuery
            ->when(!$user->isAdmin(), function ($q) use ($user) {
                return $q->where(function ($subQ) use ($user) {
                    $subQ->where('requester_id', $user->id)
                         ->orWhere('current_approver_id', $user->id);
                });
            })
            ->first();

        if (!$request) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 404,
                    'message' => 'Request not found or access denied'
                ]
            ], 404);
        }

        $approvals = Approval::with('approver')
            ->where('request_id', $request->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $approvals
        ]);
    }

    /**
     * Select a quote for a request (Accountant only)
     */
    public function selectQuote(HttpRequest $httpRequest, string $requestId): JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== 'FINAL_MANAGER' && $user->role !== 'ADMIN') {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 403, 'message' => 'Only Final Managers can select quotes' ]
            ], 403);
        }

        $validator = Validator::make($httpRequest->all(), [
            'quote_id' => 'nullable|integer|exists:request_quotes,id',
            'auto_lowest' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 422, 'message' => 'Validation failed', 'details' => $validator->errors()->all() ]
            ], 422);
        }

        $requestQuery = Request::with(['quotes']);
        if (ctype_digit($requestId)) {
            $requestQuery->where('id', $requestId);
        } else {
            $requestQuery->where('request_id', $requestId);
        }
        $req = $requestQuery->first();

        if (!$req) {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 404, 'message' => 'Request not found' ]
            ], 404);
        }

        if ($req->state !== 'DM_APPROVED') {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 400, 'message' => 'Quote selection only allowed at accountant stage' ]
            ], 400);
        }

        $selectedId = $httpRequest->get('quote_id');
        if (!$selectedId && $httpRequest->boolean('auto_lowest', true)) {
            $selected = $req->quotes->sortBy('quote_total')->first();
            $selectedId = $selected?->id;
        }

        if (!$selectedId) {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 422, 'message' => 'No quote selected or available' ]
            ], 422);
        }

        // Ensure the quote belongs to this request
        if (!$req->quotes->contains('id', $selectedId)) {
            return response()->json([
                'success' => false,
                'error' => [ 'code' => 422, 'message' => 'Selected quote does not belong to this request' ]
            ], 422);
        }

        $req->selected_quote_id = $selectedId;
        $req->save();

        // Send notifications
        $selectedQuote = $req->quotes->firstWhere('id', $selectedId);
        if ($selectedQuote) {
            NotificationService::quoteSelected($req, $selectedQuote->vendor_name, $selectedQuote->quote_total);
        }

        return response()->json([
            'success' => true,
            'message' => 'Quote selected successfully',
            'data' => $req->fresh(['selectedQuote', 'directManager'])
        ]);
    }

    /**
     * Get approval stage from user role
     */
    private function getApprovalStage(string $role): string
    {
        return match($role) {
            'DIRECT_MANAGER' => 'DM',
            'FINAL_MANAGER' => 'FINAL',
            'ACCOUNTANT' => 'ACCT',
            default => 'DM'
        };
    }

    /**
     * Find next approver based on request state
     */
    private function findNextApprover(Request $request, string $newState): ?User
    {
        // For now, we only assign a specific approver for DM; accountant is pool-based
        return null;
    }
}
