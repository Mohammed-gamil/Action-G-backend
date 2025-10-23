<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Equipment Exit Permit - {{ $request->request_id }}</title>
    <style>
        /* Prefer Cairo if present in public/fonts; fallback to Amiri, then DejaVu */
        @font-face {
            font-family: 'Cairo';
            src: url('{{ public_path('fonts/Cairo-Regular.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Cairo';
            src: url('{{ public_path('fonts/Cairo-Bold.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @font-face {
            font-family: 'Amiri';
            src: url('{{ public_path('vendor/gpdf/fonts/Amiri-Regular.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Amiri';
            src: url('{{ public_path('vendor/gpdf/fonts/Amiri-Bold.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }
        @page {
            margin: 20px;
        }
        body {
            font-family: 'Cairo', 'Amiri', 'DejaVu Sans', sans-serif;
            direction: rtl;
            unicode-bidi: bidi-override; /* force RTL ordering for mixed content */
            text-align: right;
            font-size: 12px;
            line-height: 1.6;
        }
        h1, h2, h3, p, span, td, th, label, strong, .info-label, .info-value {
            direction: rtl;
            unicode-bidi: embed; /* keep Arabic words in proper order */
        }
        /* Header styled like Visit Details print */
        .header {
            background: #1d4ed8; /* solid fallback */
            background: linear-gradient(to left, #2563eb, #1d4ed8);
            color: #fff;
            padding: 16px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .header h1 { font-size: 22px; margin: 0 0 6px 0; }
        .header h2, .header p { margin: 0; font-size: 13px; color: #eef2ff; }

        /* Section blocks */
        .section {
            margin-bottom: 16px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: #f9fafb;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #1f2937;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 40%;
            color: #374151;
        }
        .info-value {
            display: table-cell;
            color: #6b7280;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            direction: rtl;
            unicode-bidi: embed;
        }
        .items-table th {
            background-color: #1d4ed8;
            color: white;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            direction: rtl;
            unicode-bidi: embed;
        }
        .items-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: center;
            direction: rtl;
            unicode-bidi: embed;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 11px;
        }
        .status-submitted { background-color: #dbeafe; color: #1e40af; }
        .status-dm_approved { background-color: #d1fae5; color: #065f46; }
        .status-final_approved { background-color: #d1fae5; color: #065f46; }
        .status-returned { background-color: #e9d5ff; color: #6b21a8; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            color: #9ca3af;
            font-size: 10px;
        }
        .signature-section {
            margin-top: 40px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            padding: 10px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>نموذج إذن خروج معدات تصوير</h1>
        <p style="margin: 6px 0 10px 0;">
            <strong>رقم الطلب / Request ID:</strong> {{ $request->request_id }}
        </p>
        <p>
            <span class="status-badge status-{{ str_replace('_', '-', $request->status) }}">
                {{ strtoupper(str_replace('_', ' ', $request->status)) }}
            </span>
        </p>
    </div>

    <!-- Employee Information -->
    <div class="section">
        <div class="section-title">معلومات الموظف المسؤول / Employee Information</div>
        <div class="info-row">
            <span class="info-label">اسم الموظف / Employee Name:</span>
            <span class="info-value">{{ $request->employee_name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">الوظيفة / Position:</span>
            <span class="info-value">{{ $request->employee_position ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">رقم الجوال / Mobile:</span>
            <span class="info-value">{{ $request->employee_phone ?? 'N/A' }}</span>
        </div>
    </div>

    <!-- Exit Details -->
    <div class="section">
        <div class="section-title">تفاصيل الخروج / Exit Details</div>
        <div class="info-row">
            <span class="info-label">العنوان / Title:</span>
            <span class="info-value">{{ $request->title }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">الوصف / Description:</span>
            <span class="info-value">{{ $request->description ?? 'N/A' }}</span>
        </div>
        @if($request->client_entity_name)
        <div class="info-row">
            <span class="info-label">اسم العميل/الجهة / Client/Entity:</span>
            <span class="info-value">{{ $request->client_entity_name }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">موقع التصوير / Shooting Location:</span>
            <span class="info-value">{{ $request->shooting_location ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">تاريخ الخروج / Exit Date:</span>
            <span class="info-value">{{ $request->exit_date ? date('Y-m-d', strtotime($request->exit_date)) : 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">تاريخ العودة المتوقع / Expected Return Date:</span>
            <span class="info-value">{{ $request->expected_return_date ? date('Y-m-d', strtotime($request->expected_return_date)) : 'N/A' }}</span>
        </div>
    </div>

    <!-- Equipment Items -->
    <div class="section">
        <div class="section-title">المعدات المطلوبة / Equipment Items</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">اسم المعدة / Item Name</th>
                    <th style="width: 15%;">الكود / Code</th>
                    <th style="width: 12%;">الكمية المطلوبة / Qty Requested</th>
                    <th style="width: 12%;">الكمية المعتمدة / Qty Approved</th>
                    <th style="width: 13%;">الرقم التسلسلي / Serial No.</th>
                    <th style="width: 13%;">الحالة / Condition</th>
                </tr>
            </thead>
            <tbody>
                @foreach($request->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td style="text-align: right;">{{ $item->inventoryItem->name ?? 'N/A' }}</td>
                    <td>{{ $item->inventoryItem->code ?? 'N/A' }}</td>
                    <td>{{ $item->quantity_requested }}</td>
                    <td>{{ $item->quantity_approved ?? '-' }}</td>
                    <td>{{ $item->serial_number ?? 'N/A' }}</td>
                    <td>{{ $item->condition_before_exit ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Approval Information -->
    @if($request->directManager || $request->warehouseManager)
    <div class="section">
        <div class="section-title">معلومات الموافقة / Approval Information</div>
        @if($request->directManager)
        <div class="info-row">
            <span class="info-label">المدير المباشر / Direct Manager:</span>
            <span class="info-value">{{ $request->directManager->name }}</span>
        </div>
        @endif
        @if($request->warehouseManager)
        <div class="info-row">
            <span class="info-label">مدير المستودع / Warehouse Manager:</span>
            <span class="info-value">{{ $request->warehouseManager->name }}</span>
        </div>
        @endif
        @if($request->rejection_reason)
        <div class="info-row">
            <span class="info-label">سبب الرفض / Rejection Reason:</span>
            <span class="info-value">{{ $request->rejection_reason }}</span>
        </div>
        @endif
    </div>
    @endif

    <!-- Signatures -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <strong>توقيع الموظف المستلم</strong><br>
                Employee Signature
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <strong>توقيع مسؤول المستودع</strong><br>
                Warehouse Supervisor Signature
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i:s') }} | Printed: {{ now()->format('Y-m-d H:i:s') }}</p>
        <p>Action Group - Production & Photography Department</p>
    </div>

    <div class="section">
    <p><span class="label">Exit Purpose:</span> {{ $request->exit_purpose ?? '-' }} {{ $request->custom_exit_purpose ? '(' . $request->custom_exit_purpose . ')' : '' }}</p>
    <p><span class="label">Shoot Location:</span> {{ $request->shoot_location ?? '-' }}</p>
    <p><span class="label">Return Supervisor:</span> {{ $request->return_supervisor_name ?? '-' }} | {{ $request->return_supervisor_phone ?? '-' }}</p>
  </div>

  <div style="margin-top:12px; font-size:11px; color:#666">Generated at {{ now()->toDateTimeString() }}</div>
</body>
</html>
