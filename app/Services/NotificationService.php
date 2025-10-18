<?php

namespace App\Services;

use App\Models\User;
use App\Models\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public static function create(User $user, string $type, array $data): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
            'data' => json_encode($data),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Notify when a new request is submitted
     */
    public static function requestSubmitted(Request $request): void
    {
        // Notify all direct managers for purchase requests
        if ($request->type === 'purchase') {
            $directManagers = User::where('role', 'DIRECT_MANAGER')
                ->where('status', 'active')
                ->get();

            foreach ($directManagers as $manager) {
                self::create($manager, 'request_submitted', [
                    'message' => "{$request->requester->name} submitted a new purchase request: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'requester_name' => $request->requester->name,
                    'title' => $request->title,
                    'type' => $request->type,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }

        // For project requests, notify final managers directly
        if ($request->type === 'project') {
            $finalManagers = User::where('role', 'FINAL_MANAGER')
                ->where('status', 'active')
                ->get();

            foreach ($finalManagers as $manager) {
                self::create($manager, 'request_submitted', [
                    'message' => "{$request->requester->name} submitted a new project request: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'requester_name' => $request->requester->name,
                    'title' => $request->title,
                    'type' => $request->type,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }

        // Notify the assigned direct manager if specified
        if ($request->direct_manager_id) {
            $directManager = User::find($request->direct_manager_id);
            if ($directManager) {
                self::create($directManager, 'request_assigned', [
                    'message' => "You have been assigned as direct manager for: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'requester_name' => $request->requester->name,
                    'title' => $request->title,
                    'type' => $request->type,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }
    }

    /**
     * Notify when a request is approved
     */
    public static function requestApproved(Request $request, User $approver, string $stage): void
    {
        // Notify the requester
        self::create($request->requester, 'request_approved', [
            'message' => "{$approver->name} approved your request: {$request->title}",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'approver_name' => $approver->name,
            'title' => $request->title,
            'stage' => $stage,
            'state' => $request->state,
            'action_url' => "/requests/{$request->id}",
        ]);

        // If request moves to next stage, notify next approvers
        if ($request->state === 'DM_APPROVED') {
            // Notify final managers
            $finalManagers = User::where('role', 'FINAL_MANAGER')
                ->where('status', 'active')
                ->get();

            foreach ($finalManagers as $manager) {
                self::create($manager, 'request_pending_approval', [
                    'message' => "Request ready for final approval: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'requester_name' => $request->requester->name,
                    'title' => $request->title,
                    'type' => $request->type,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }

        if ($request->state === 'FINAL_APPROVED') {
            // Notify accountants
            $accountants = User::where('role', 'ACCOUNTANT')
                ->where('status', 'active')
                ->get();

            foreach ($accountants as $accountant) {
                self::create($accountant, 'request_pending_payment', [
                    'message' => "Request ready for payment processing: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'requester_name' => $request->requester->name,
                    'title' => $request->title,
                    'type' => $request->type,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }

        if ($request->state === 'PROCESSING' && $request->type === 'project') {
            // Notify requester that project is now active
            self::create($request->requester, 'project_started', [
                'message' => "Your project '{$request->title}' is now in progress. Inventory items have been reserved.",
                'request_id' => $request->id,
                'request_number' => $request->request_id,
                'title' => $request->title,
                'action_url' => "/requests/{$request->id}",
            ]);
        }
    }

    /**
     * Notify when a request is rejected
     */
    public static function requestRejected(Request $request, User $rejector, string $comment): void
    {
        // Notify the requester
        self::create($request->requester, 'request_rejected', [
            'message' => "{$rejector->name} rejected your request: {$request->title}",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'rejector_name' => $rejector->name,
            'title' => $request->title,
            'comment' => $comment,
            'action_url' => "/requests/{$request->id}",
        ]);
    }

    /**
     * Notify when a quote is uploaded
     */
    public static function quoteUploaded(Request $request, string $vendorName, float $quoteTotal): void
    {
        // Notify final managers
        $finalManagers = User::where('role', 'FINAL_MANAGER')
            ->where('status', 'active')
            ->get();

        foreach ($finalManagers as $manager) {
            self::create($manager, 'quote_uploaded', [
                'message' => "New quote uploaded for: {$request->title} - {$vendorName} (\${$quoteTotal})",
                'request_id' => $request->id,
                'request_number' => $request->request_id,
                'vendor_name' => $vendorName,
                'quote_total' => $quoteTotal,
                'title' => $request->title,
                'action_url' => "/requests/{$request->id}",
            ]);
        }

        // Notify requester
        self::create($request->requester, 'quote_uploaded', [
            'message' => "A vendor quote has been uploaded for your request: {$request->title}",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'vendor_name' => $vendorName,
            'quote_total' => $quoteTotal,
            'title' => $request->title,
            'action_url' => "/requests/{$request->id}",
        ]);
    }

    /**
     * Notify when a quote is selected
     */
    public static function quoteSelected(Request $request, string $vendorName, float $quoteTotal): void
    {
        // Notify requester
        self::create($request->requester, 'quote_selected', [
            'message' => "Quote selected for your request: {$request->title} - {$vendorName} (\${$quoteTotal})",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'vendor_name' => $vendorName,
            'quote_total' => $quoteTotal,
            'title' => $request->title,
            'action_url' => "/requests/{$request->id}",
        ]);

        // Notify accountants if request is ready for payment
        if ($request->state === 'DM_APPROVED') {
            $accountants = User::where('role', 'ACCOUNTANT')
                ->where('status', 'active')
                ->get();

            foreach ($accountants as $accountant) {
                self::create($accountant, 'quote_selected', [
                    'message' => "Quote selected for: {$request->title} - Ready for final approval",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'vendor_name' => $vendorName,
                    'quote_total' => $quoteTotal,
                    'title' => $request->title,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }
    }

    /**
     * Notify when payment is transferred
     */
    public static function fundsTransferred(Request $request, string $reference): void
    {
        // Notify requester
        self::create($request->requester, 'funds_transferred', [
            'message' => "Funds have been transferred for your request: {$request->title}",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'title' => $request->title,
            'reference' => $reference,
            'action_url' => "/requests/{$request->id}",
        ]);
    }

    /**
     * Notify when project is marked as done
     */
    public static function projectMarkedDone(Request $request): void
    {
        // Notify accountants to confirm client payment
        $accountants = User::where('role', 'ACCOUNTANT')
            ->where('status', 'active')
            ->get();

        foreach ($accountants as $accountant) {
            self::create($accountant, 'project_done', [
                'message' => "Project completed and awaiting payment confirmation: {$request->title}",
                'request_id' => $request->id,
                'request_number' => $request->request_id,
                'title' => $request->title,
                'requester_name' => $request->requester->name,
                'action_url' => "/requests/{$request->id}",
            ]);
        }

        // Notify direct manager
        if ($request->direct_manager_id) {
            $directManager = User::find($request->direct_manager_id);
            if ($directManager) {
                self::create($directManager, 'project_done', [
                    'message' => "Project marked as done: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'title' => $request->title,
                    'requester_name' => $request->requester->name,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }
    }

    /**
     * Notify when client payment is confirmed
     */
    public static function clientPaymentConfirmed(Request $request): void
    {
        // Notify requester
        self::create($request->requester, 'payment_confirmed', [
            'message' => "Client payment confirmed for project: {$request->title}",
            'request_id' => $request->id,
            'request_number' => $request->request_id,
            'title' => $request->title,
            'action_url' => "/requests/{$request->id}",
        ]);

        // Notify direct manager
        if ($request->direct_manager_id) {
            $directManager = User::find($request->direct_manager_id);
            if ($directManager) {
                self::create($directManager, 'payment_confirmed', [
                    'message' => "Client payment confirmed for project: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'title' => $request->title,
                    'requester_name' => $request->requester->name,
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }
    }

    /**
     * Notify when a comment is added to a request
     */
    public static function commentAdded(Request $request, User $commenter, string $comment): void
    {
        // Notify requester if they're not the commenter
        if ($request->requester_id !== $commenter->id) {
            self::create($request->requester, 'comment_added', [
                'message' => "{$commenter->name} commented on your request: {$request->title}",
                'request_id' => $request->id,
                'request_number' => $request->request_id,
                'commenter_name' => $commenter->name,
                'title' => $request->title,
                'comment' => substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : ''),
                'action_url' => "/requests/{$request->id}",
            ]);
        }

        // Notify direct manager if they exist and aren't the commenter
        if ($request->direct_manager_id && $request->direct_manager_id !== $commenter->id) {
            $directManager = User::find($request->direct_manager_id);
            if ($directManager) {
                self::create($directManager, 'comment_added', [
                    'message' => "{$commenter->name} commented on request: {$request->title}",
                    'request_id' => $request->id,
                    'request_number' => $request->request_id,
                    'commenter_name' => $commenter->name,
                    'title' => $request->title,
                    'comment' => substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : ''),
                    'action_url' => "/requests/{$request->id}",
                ]);
            }
        }
    }
}
