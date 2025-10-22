<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>استلام معدات التصوير بعد العودة - {{ $request->request_id }}</title>
  <style>
    @font-face {
      font-family: 'DejaVu Sans';
      src: url('/fonts/DejaVuSans.ttf') format('truetype');
    }
    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 11px;
      color: #222;
      direction: rtl;
      text-align: right;
    }
    .header {
      text-align: center;
      margin-bottom: 20px;
      border-bottom: 2px solid #4F46E5;
      padding-bottom: 10px;
    }
    .header h1 {
      font-size: 18px;
      margin: 0 0 5px 0;
      color: #4F46E5;
    }
    .header h2 {
      font-size: 14px;
      margin: 0;
      color: #6B7280;
    }
    .section {
      margin-bottom: 15px;
      padding: 10px;
      border: 1px solid #E5E7EB;
      border-radius: 5px;
    }
    .section-title {
      font-size: 13px;
      font-weight: 700;
      color: #4F46E5;
      margin-bottom: 8px;
      border-bottom: 1px solid #E5E7EB;
      padding-bottom: 5px;
    }
    .field {
      margin-bottom: 8px;
      display: flex;
      justify-content: space-between;
    }
    .field-label {
      font-weight: 700;
      color: #374151;
      width: 35%;
    }
    .field-value {
      color: #6B7280;
      width: 63%;
      text-align: left;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      padding: 8px 6px;
      border: 1px solid #D1D5DB;
      text-align: right;
    }
    th {
      background-color: #F3F4F6;
      font-weight: 700;
      font-size: 11px;
      color: #374151;
    }
    td {
      font-size: 10px;
      color: #6B7280;
    }
    .status-badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 10px;
      font-weight: 700;
    }
    .status-excellent { background-color: #D1FAE5; color: #065F46; }
    .status-cleaning { background-color: #FEF3C7; color: #92400E; }
    .status-maintenance { background-color: #FED7AA; color: #9A3412; }
    .status-damaged { background-color: #FEE2E2; color: #991B1B; }
    .footer {
      margin-top: 30px;
      padding-top: 15px;
      border-top: 1px solid #E5E7EB;
      font-size: 10px;
      color: #9CA3AF;
      text-align: center;
    }
    .signature-box {
      margin-top: 20px;
      display: flex;
      justify-content: space-between;
    }
    .signature {
      width: 45%;
      padding: 10px;
      border: 1px dashed #D1D5DB;
      text-align: center;
    }
    .signature-title {
      font-weight: 700;
      margin-bottom: 30px;
    }
    .signature-line {
      border-top: 1px solid #374151;
      margin-top: 30px;
      padding-top: 5px;
    }
  </style>
</head>
<body>
  <div class="header">
    <h1>استلام معدات التصوير بعد العودة</h1>
    <h2>Action Group</h2>
    <p style="margin: 5px 0; font-size: 12px;">رقم الطلب: {{ $request->request_id }}</p>
  </div>

  <!-- Section 1: معلومات المستخدم والطلب -->
  <div class="section">
    <div class="section-title">1. معلومات المستخدم والطلب</div>
    <div class="field">
      <span class="field-label">اسم الموظف:</span>
      <span class="field-value">{{ $request->employee_name ?? $request->requester->name ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">المسمى الوظيفي:</span>
      <span class="field-value">{{ $request->employee_position ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">رقم الجوال:</span>
      <span class="field-value">{{ $request->employee_phone ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">عنوان الطلب:</span>
      <span class="field-value">{{ $request->title }}</span>
    </div>
    <div class="field">
      <span class="field-label">رقم الطلب:</span>
      <span class="field-value">{{ $request->request_id }}</span>
    </div>
  </div>

  <!-- Section 2: تفاصيل الإرجاع -->
  <div class="section">
    <div class="section-title">2. تفاصيل الإرجاع</div>
    <div class="field">
      <span class="field-label">تاريخ الإرجاع:</span>
      <span class="field-value">{{ optional($request->return_date)->format('Y-m-d') ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">اسم المشرف المستلم:</span>
      <span class="field-value">{{ $request->return_supervisor_name ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">رقم جوال المشرف:</span>
      <span class="field-value">{{ $request->return_supervisor_phone ?? '-' }}</span>
    </div>
    <div class="field">
      <span class="field-label">أعيد بواسطة:</span>
      <span class="field-value">{{ $request->returned_by_employee ?? $request->employee_name ?? '-' }}</span>
    </div>
  </div>

  <!-- Section 3: قائمة المعدات المرتجعة -->
  <div class="section">
    <div class="section-title">3. قائمة المعدات المرتجعة</div>
    <table>
      <thead>
        <tr>
          <th style="width: 5%;">#</th>
          <th style="width: 25%;">اسم المعدة</th>
          <th style="width: 10%;">الرقم التسلسلي</th>
          <th style="width: 10%;">الكمية المطلوبة</th>
          <th style="width: 10%;">الكمية المرتجعة</th>
          <th style="width: 15%;">الحالة قبل الخروج</th>
          <th style="width: 15%;">الحالة بعد العودة</th>
          <th style="width: 10%;">ملاحظات</th>
        </tr>
      </thead>
      <tbody>
        @foreach($request->items as $idx => $item)
        <tr>
          <td>{{ $idx + 1 }}</td>
          <td style="font-weight: 600;">{{ $item->inventoryItem->name ?? 'معدة #' . $item->inventory_item_id }}</td>
          <td>{{ $item->serial_number ?? '-' }}</td>
          <td>{{ $item->quantity_requested }}</td>
          <td style="font-weight: 700; color: {{ $item->quantity_returned == $item->quantity_requested ? '#059669' : '#DC2626' }};">
            {{ $item->quantity_returned ?? 0 }}
          </td>
          <td>{{ $item->condition_before_exit ?? '-' }}</td>
          <td>
            @if($item->condition_after_return)
              @php
                $conditionMap = [
                  'excellent' => 'ممتازة',
                  'good' => 'جيدة',
                  'needs_cleaning' => 'تحتاج تنظيف',
                  'needs_maintenance' => 'تحتاج صيانة',
                  'damaged' => 'تالفة',
                  'lost' => 'مفقودة'
                ];
                $conditionClass = [
                  'excellent' => 'status-excellent',
                  'good' => 'status-excellent',
                  'needs_cleaning' => 'status-cleaning',
                  'needs_maintenance' => 'status-maintenance',
                  'damaged' => 'status-damaged',
                  'lost' => 'status-damaged'
                ];
              @endphp
              <span class="status-badge {{ $conditionClass[$item->condition_after_return] ?? '' }}">
                {{ $conditionMap[$item->condition_after_return] ?? $item->condition_after_return }}
              </span>
            @else
              -
            @endif
          </td>
          <td style="font-size: 9px;">{{ $item->return_notes ?? '-' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <!-- Section 4: الحالة العامة للمعدات -->
  <div class="section">
    <div class="section-title">4. الحالة العامة للمعدات بعد العودة</div>
    <div class="field">
      <span class="field-label">التقييم العام:</span>
      <span class="field-value">
        @php
          $statusMap = [
            'excellent' => 'ممتازة - جميع المعدات بحالة ممتازة',
            'needs_cleaning' => 'تحتاج تنظيف - بعض المعدات تحتاج تنظيف فقط',
            'needs_maintenance' => 'تحتاج صيانة - بعض المعدات تحتاج صيانة',
            'damaged_or_lost' => 'تالفة أو مفقودة - يوجد معدات تالفة أو مفقودة'
          ];
        @endphp
        <strong>{{ $statusMap[$request->equipment_condition_on_return] ?? $request->equipment_condition_on_return ?? 'غير محدد' }}</strong>
      </span>
    </div>
  </div>

  <!-- Section 5: ملاحظات المشرف -->
  <div class="section">
    <div class="section-title">5. ملاحظات المشرف</div>
    <p style="margin: 0; min-height: 50px; padding: 8px; background-color: #F9FAFB; border-radius: 4px;">
      {{ $request->supervisor_notes ?? 'لا توجد ملاحظات إضافية' }}
    </p>
  </div>

  <!-- Section 6: التوقيعات والإقرارات -->
  <div class="signature-box">
    <div class="signature">
      <div class="signature-title">توقيع الموظف المستلم</div>
      <div class="signature-line">
        {{ $request->return_supervisor_name ?? '..............................' }}
      </div>
    </div>
    <div class="signature">
      <div class="signature-title">توقيع الموظف المُرجع</div>
      <div class="signature-line">
        {{ $request->returned_by_employee ?? $request->employee_name ?? '..............................' }}
      </div>
    </div>
  </div>

  <div class="footer">
    <p style="margin: 5px 0;">تم إنشاء هذا الإيصال بتاريخ: {{ now()->format('Y-m-d H:i:s') }}</p>
    <p style="margin: 5px 0;">Action Group - نظام إدارة المعدات</p>
    <p style="margin: 5px 0; font-size: 9px;">هذا المستند إلكتروني ولا يتطلب ختماً أو توقيعاً رسمياً</p>
  </div>
</body>
</html>
