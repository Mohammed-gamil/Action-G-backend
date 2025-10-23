<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>استلام معدات التصوير بعد العودة - {{ $request->request_id }}</title>
<style>
  /* Fonts */
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

  /* Base */
  body { font-family: 'Cairo','Amiri','DejaVu Sans', Arial, sans-serif; font-size: 12px; color:#111827; direction: rtl; unicode-bidi: embed; text-align: right; }
  h1, h2, h3, p, span, td, th, label, strong { direction: rtl; unicode-bidi: embed; }

  /* Header like Visit print */
  .header { background:#1d4ed8; background: linear-gradient(to left, #2563eb, #1d4ed8); color:#fff; padding:16px 20px; margin-bottom:20px; border-radius:8px; text-align:center }
  .header h1 { font-size:20px; margin:0 0 6px 0 }
  .header h2, .header p { margin:0; font-size:13px; color:#eef2ff }

  /* Sections */
  .section { margin-bottom:16px; padding:12px 14px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb }
  .section-title { font-size:14px; font-weight:700; margin-bottom:8px; color:#1f2937 }

  /* Fields */
  .field { margin-bottom:8px }
  .field-label { display:inline-block; min-width:160px; font-weight:700; color:#374151 }
  .field-value { display:inline-block; color:#111827 }

  /* Table */
  table { width:100%; border-collapse: collapse; direction: rtl; unicode-bidi: embed }
  th { background:#1d4ed8; color:#fff; padding:8px 10px; text-align:center; font-weight:700 }
  td { border:1px solid #e5e7eb; padding:8px 10px; text-align:center }
  tbody tr:nth-child(even) { background:#f9fafb }

  /* Status badges for condition */
  .status-badge { display:inline-block; padding:4px 10px; border-radius:9999px; font-size:11px; font-weight:700 }
  .status-excellent { background:#d1fae5; color:#065f46 }
  .status-cleaning { background:#fef3c7; color:#92400e }
  .status-maintenance { background:#fee2e2; color:#991b1b }
  .status-damaged { background:#fee2e2; color:#991b1b }

  /* Signatures */
  .signature-box { display: table; width: 100%; margin-top: 20px }
  .signature { display: table-cell; width: 50%; padding: 10px; text-align: center }
  .signature-title { font-weight:700; margin-bottom: 8px }
  .signature-line { border-top:1px solid #000; margin-top: 24px; padding-top: 4px }

  /* Footer */
  .footer { margin-top: 16px; padding-top: 12px; border-top: 1px solid #e5e7eb; text-align: center; color:#6b7280; font-size: 11px }

  /* LTR helper */
  .ltr-text { direction:ltr; text-align:left; unicode-bidi: bidi-override }
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
