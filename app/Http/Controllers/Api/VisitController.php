<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\Client;
use App\Models\BusinessType;
use App\Models\ProductCategory;
use App\Models\VisitFile;
use App\Models\VisitStatusHistory;
use App\Exports\VisitsExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    /**
     * Get visits (reps see only theirs, admins see all)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Visit::with(['client.businessType', 'productCategory', 'files']);

        // Role-based filtering
        if ($user->role === 'SALES_REP') {
            $query->forRep($user->id);
        } elseif ($request->has('rep_id')) {
            $query->forRep($request->rep_id);
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        // Date range filter
        if ($request->has('date_from') || $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Business type filter
        if ($request->has('business_type_id')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('business_type_id', $request->business_type_id);
            });
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $visits = $query->orderBy('visit_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $visits->items(),
            'pagination' => [
                'total' => $visits->total(),
                'per_page' => $visits->perPage(),
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
            ],
        ]);
    }

    /**
     * Get single visit
     */
    public function show($id)
    {
        $user = Auth::user();
        $visit = Visit::with([
            'client.businessType',
            'productCategory',
            'salesRep',
            'files',
            'statusHistory.changedBy'
        ])->findOrFail($id);

        // Authorization check
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $visit,
        ]);
    }

    /**
     * Create visit
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:tbl_clients,id',
            'visit_date' => 'required|date',
            'has_previous_agency' => 'boolean',
            'previous_agency_name' => 'nullable|string|max:200',
            'needs_voiceover' => 'boolean',
            'voiceover_language' => 'nullable|string|max:50',
            'shooting_goals' => 'nullable|array',
            'shooting_goals_other_text' => 'nullable|string',
            'service_types' => 'nullable|array',
            'service_types_other_text' => 'nullable|string',
            'preferred_location' => 'nullable|in:client_location,action_studio,external',
            'product_category_id' => 'nullable|exists:tbl_product_categories,id',
            'product_description' => 'nullable|string',
            'estimated_product_count' => 'nullable|integer|min:1',
            'preferred_shoot_date' => 'nullable|date',
            'budget_range' => 'nullable|string|max:100',
            'rep_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $data = $validator->validated();
        $data['rep_id'] = $user->id;
        $data['status'] = 'draft';

        $visit = Visit::create($data);

        // Log status history
        VisitStatusHistory::create([
            'visit_id' => $visit->id,
            'from_status' => null,
            'to_status' => 'draft',
            'changed_by_user_id' => $user->id,
            'notes' => 'Visit created',
        ]);

        return response()->json([
            'success' => true,
            'data' => $visit->load(['client.businessType', 'productCategory']),
            'message' => 'Visit created successfully',
        ], 201);
    }

    /**
     * Update visit
     */
    public function update(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $user = Auth::user();

        // Authorization: reps can only edit their own draft visits
        if ($user->role === 'SALES_REP') {
            if ($visit->rep_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }
            if ($visit->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit submitted visits'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'client_id' => 'sometimes|exists:tbl_clients,id',
            'visit_date' => 'sometimes|date',
            'has_previous_agency' => 'boolean',
            'previous_agency_name' => 'nullable|string|max:200',
            'needs_voiceover' => 'boolean',
            'voiceover_language' => 'nullable|string|max:50',
            'shooting_goals' => 'nullable|array',
            'shooting_goals_other_text' => 'nullable|string',
            'service_types' => 'nullable|array',
            'service_types_other_text' => 'nullable|string',
            'preferred_location' => 'nullable|in:client_location,action_studio,external',
            'product_category_id' => 'nullable|exists:tbl_product_categories,id',
            'product_description' => 'nullable|string',
            'estimated_product_count' => 'nullable|integer|min:1',
            'preferred_shoot_date' => 'nullable|date',
            'budget_range' => 'nullable|string|max:100',
            'rep_notes' => 'nullable|string',
            'admin_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $visit->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $visit->load(['client.businessType', 'productCategory']),
            'message' => 'Visit updated successfully',
        ]);
    }

    /**
     * Update visit status (Admin only)
     */
    public function updateStatus(Request $request, $id)
    {
        $user = Auth::user();
        
        // Sales reps and admins can change status
        if (!in_array($user->role, ['ADMIN', 'SUPER_ADMIN', 'SALES_REP'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators and sales representatives can change visit status'
            ], 403);
        }

        $visit = Visit::findOrFail($id);
        
        // Sales reps can only change status of their own visits
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only change status of your own visits'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:draft,submitted,pending_review,action_required,approved,quotation_sent,closed_won,closed_lost',
            'notes' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'action_required_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $oldStatus = $visit->status;
        $newStatus = $request->status;

        // Update visit
        $visit->status = $newStatus;
        if ($request->has('admin_notes')) {
            $visit->admin_notes = $request->admin_notes;
        }
        if ($request->has('action_required_message')) {
            $visit->action_required_message = $request->action_required_message;
        }

        // Set timestamps for specific statuses
        if ($newStatus === 'submitted' && !$visit->submitted_at) {
            $visit->submitted_at = now();
        }
        if ($newStatus === 'approved' && !$visit->approved_at) {
            $visit->approved_at = now();
            $visit->approved_by_admin_id = $user->id;
        }

        $visit->save();

        // Log status history
        VisitStatusHistory::create([
            'visit_id' => $visit->id,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'changed_by_user_id' => $user->id,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $visit->load(['client.businessType', 'productCategory', 'statusHistory.changedBy']),
            'message' => 'Visit status updated successfully',
        ]);
    }

    /**
     * Add notes to a visit (rep_notes or admin_notes)
     */
    public function addNotes(Request $request, $id)
    {
        $user = Auth::user();
        $visit = Visit::findOrFail($id);
        
        // Sales reps can only add notes to their own visits
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add notes to your own visits'
            ], 403);
        }
        
        // Only sales reps and admins can add notes
        if (!in_array($user->role, ['ADMIN', 'SUPER_ADMIN', 'SALES_REP'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to add notes'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'rep_notes' => 'nullable|string',
            'admin_notes' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Sales reps add rep_notes, admins add admin_notes
        // Append new notes with timestamp and user name instead of replacing
        $timestamp = now()->format('Y-m-d H:i:s');
        $userName = $user->name ?? $user->email;
        
        if ($user->role === 'SALES_REP' && $request->has('rep_notes')) {
            $newNote = "[{$timestamp}] {$userName}: {$request->rep_notes}";
            $visit->rep_notes = $visit->rep_notes 
                ? $visit->rep_notes . "\n\n" . $newNote 
                : $newNote;
        }
        
        if (in_array($user->role, ['ADMIN', 'SUPER_ADMIN']) && $request->has('admin_notes')) {
            $newNote = "[{$timestamp}] {$userName}: {$request->admin_notes}";
            $visit->admin_notes = $visit->admin_notes 
                ? $visit->admin_notes . "\n\n" . $newNote 
                : $newNote;
        }
        
        $visit->save();
        
        return response()->json([
            'success' => true,
            'data' => $visit->load(['client.businessType', 'productCategory']),
            'message' => 'Notes added successfully',
        ]);
    }

    /**
     * Get visit status history
     */
    public function getHistory($id)
    {
        $visit = Visit::findOrFail($id);
        $user = Auth::user();

        // Authorization
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $history = $visit->statusHistory()->with('changedBy')->orderBy('changed_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Get visit statistics
     */
    public function getStats(Request $request)
    {
        $user = Auth::user();
        $query = Visit::query();

        // Role-based filtering
        if ($user->role === 'SALES_REP') {
            $query->forRep($user->id);
        } elseif ($request->has('rep_id')) {
            $query->forRep($request->rep_id);
        }

        // Date range filter
        if ($request->has('date_from') || $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        $total = $query->count();
        $draft = (clone $query)->where('status', 'draft')->count();
        $submitted = (clone $query)->where('status', 'submitted')->count();
        $pendingReview = (clone $query)->where('status', 'pending_review')->count();
        $approved = (clone $query)->where('status', 'approved')->count();
        $quotationSent = (clone $query)->where('status', 'quotation_sent')->count();
        $closedWon = (clone $query)->where('status', 'closed_won')->count();
        $closedLost = (clone $query)->where('status', 'closed_lost')->count();

        // This week
        $thisWeek = (clone $query)->where('visit_date', '>=', now()->startOfWeek())->count();
        
        // This month
        $thisMonth = (clone $query)->where('visit_date', '>=', now()->startOfMonth())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'draft' => $draft,
                'submitted' => $submitted,
                'pending_review' => $pendingReview,
                'approved' => $approved,
                'quotation_sent' => $quotationSent,
                'closed_won' => $closedWon,
                'closed_lost' => $closedLost,
                'this_week' => $thisWeek,
                'this_month' => $thisMonth,
            ],
        ]);
    }

    /**
     * Upload visit file
     */
    public function uploadFile(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $user = Auth::user();

        // Authorization
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:51200', // 50MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileType = str_starts_with($mimeType, 'video/') ? 'video' : 'photo';
        
        // Generate unique filename
        $storedName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        // Store file
        $path = $file->storeAs('visits/' . $visit->id, $storedName, 'public');
        $url = Storage::url($path);

        // Create file record
        $visitFile = VisitFile::create([
            'visit_id' => $visit->id,
            'file_type' => $fileType,
            'original_filename' => $originalName,
            'stored_filename' => $storedName,
            'file_size_bytes' => $file->getSize(),
            'mime_type' => $mimeType,
            'storage_url' => $url,
            'upload_status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'data' => $visitFile,
            'message' => 'File uploaded successfully',
        ], 201);
    }

    /**
     * Delete visit file
     */
    public function deleteFile($visitId, $fileId)
    {
        $visit = Visit::findOrFail($visitId);
        $user = Auth::user();

        // Authorization
        if ($user->role === 'SALES_REP' && $visit->rep_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $file = VisitFile::where('visit_id', $visitId)->findOrFail($fileId);

        // Delete from storage
        $path = 'visits/' . $visitId . '/' . $file->stored_filename;
        Storage::disk('public')->delete($path);

        // Delete record
        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }

    /**
     * Search clients (autocomplete)
     */
    public function searchClients(Request $request)
    {
        $search = $request->get('q', '');
        
        $clients = Client::with('businessType')
            ->search($search)
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Get all clients
     */
    public function getClients(Request $request)
    {
        $query = Client::with('businessType');

        if ($request->has('search')) {
            $query->search($request->search);
        }

        $perPage = $request->get('per_page', 15);
        $clients = $query->orderBy('store_name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $clients->items(),
            'pagination' => [
                'total' => $clients->total(),
                'per_page' => $clients->perPage(),
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
            ],
        ]);
    }

    /**
     * Create client
     */
    public function createClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:200',
            'contact_person' => 'required|string|max:100',
            'mobile' => 'required|string|max:20',
            'mobile_2' => 'nullable|string|max:20',
            'address' => 'required|string',
            'business_type_id' => 'required|exists:tbl_business_types,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by_rep_id'] = Auth::id();

        $client = Client::create($data);

        return response()->json([
            'success' => true,
            'data' => $client->load('businessType'),
            'message' => 'Client created successfully',
        ], 201);
    }

    /**
     * Get business types
     */
    public function getBusinessTypes()
    {
        $types = BusinessType::active()->get();

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get product categories
     */
    public function getProductCategories()
    {
        $categories = ProductCategory::active()->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Export visits to Excel
     */
    public function exportExcel(Request $request)
    {
        $user = Auth::user();
        $query = Visit::with(['client.businessType', 'productCategory', 'salesRep']);

        // Role-based filtering
        if ($user->role === 'SALES_REP') {
            $query->forRep($user->id);
        } elseif ($request->has('rep_id')) {
            $query->forRep($request->rep_id);
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        // Date range filter
        if ($request->has('date_from') || $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Business type filter
        if ($request->has('business_type_id')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('business_type_id', $request->business_type_id);
            });
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $visits = $query->orderBy('visit_date', 'desc')->get();

        // Generate Excel file
        $export = new VisitsExport($visits);
        $phpExcel = $export->export();

        // Create Excel writer
        $writer = \PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');

        // Generate filename
        $filename = 'visits_export_' . date('Y-m-d_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'visits_export_');

        // Save to temp file
        $writer->save($tempFile);

        // Return file download
        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Export visits to PDF
     */
    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        $query = Visit::with(['client.businessType', 'productCategory', 'salesRep']);

        // Role-based filtering
        if ($user->role === 'SALES_REP') {
            $query->forRep($user->id);
        } elseif ($request->has('rep_id')) {
            $query->forRep($request->rep_id);
        }

        // Status filter
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        // Date range filter
        if ($request->has('date_from') || $request->has('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Business type filter
        if ($request->has('business_type_id')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('business_type_id', $request->business_type_id);
            });
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $visits = $query->orderBy('visit_date', 'desc')->get();

        // Generate HTML content
        $html = $this->generatePdfHtml($visits);

        // For now, we'll return a simple HTML response
        // In production, you would use a library like DomPDF or mPDF
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="visits_export_' . date('Y-m-d_His') . '.html"',
        ]);
    }

    /**
     * Generate HTML for PDF export
     */
    private function generatePdfHtml($visits)
    {
        $statusMap = [
            'draft' => 'مسودة',
            'submitted' => 'مُرسلة',
            'pending_review' => 'قيد المراجعة',
            'action_required' => 'يتطلب إجراء',
            'approved' => 'موافق عليها',
            'quotation_sent' => 'تم إرسال العرض',
            'closed_won' => 'مغلقة - فوز',
            'closed_lost' => 'مغلقة - خسارة',
        ];

        $html = '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الزيارات</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            text-align: right;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .date {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير الزيارات</h1>
    </div>
    <div class="date">
        تاريخ التصدير: ' . date('Y-m-d H:i:s') . '
    </div>
    <table>
        <thead>
            <tr>
                <th>رقم الزيارة</th>
                <th>اسم المتجر</th>
                <th>جهة الاتصال</th>
                <th>رقم الجوال</th>
                <th>نوع النشاط</th>
                <th>تاريخ الزيارة</th>
                <th>اسم المندوب</th>
                <th>الحالة</th>
                <th>فئة المنتج</th>
                <th>عدد القطع</th>
                <th>نطاق الميزانية</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($visits as $visit) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($visit->id) . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->client->store_name ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->client->contact_person ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->client->mobile ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->client->businessType->name_ar ?? '') . '</td>';
            $html .= '<td>' . ($visit->visit_date ? date('Y-m-d', strtotime($visit->visit_date)) : '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->salesRep->name ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($statusMap[$visit->status] ?? $visit->status) . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->productCategory->name_ar ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->estimated_product_count ?? '') . '</td>';
            $html .= '<td>' . htmlspecialchars($visit->budget_range ?? '') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>
    <div class="date" style="margin-top: 30px;">
        إجمالي عدد الزيارات: ' . count($visits) . '
    </div>
</body>
</html>';

        return $html;
    }
}
