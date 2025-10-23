<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>تأكيد حجز الاستوديو - {{ $booking->booking_number ?? $booking->request_id }}</title>
  <style>
    /* Prefer Cairo if present in public/fonts; fallback to Amiri */
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
  body { font-family: 'Cairo','Amiri','DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 12px; color:#111827; direction: rtl; unicode-bidi: embed; text-align: right }
    /* Header like Visit print */
    .header { background:#1d4ed8; background: linear-gradient(to left, #2563eb, #1d4ed8); color:#fff; padding:16px 20px; margin-bottom:20px; border-radius:8px; text-align:center }
    .header h2 { font-size:20px; margin:0 0 6px 0 }
    .header p { margin:0; font-size:13px; color:#eef2ff }
    /* Sections */
    .section { margin-bottom: 16px; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb }
    .section-title { font-size: 14px; font-weight: 700; margin-bottom: 8px; color: #1f2937 }
    .label { font-weight: 700; color:#374151 }
    /* Table */
    table { width:100%; border-collapse: collapse; direction: rtl; unicode-bidi: embed }
    th { background:#1d4ed8; color:#fff; padding:8px 10px; text-align:center; font-weight:700 }
    td { border:1px solid #e5e7eb; padding:8px 10px; vertical-align: top; text-align: center }
    .border { border:1px solid #e5e7eb }
  </style>
</head>
<body>
  <div class="header">
    <h2>تأكيد حجز الاستوديو</h2>
    <p>{{ $booking->booking_number ?? $booking->request_id }} - {{ $booking->title }}</p>
  </div>

  <div class="section">
    <div class="section-title">بيانات العميل</div>
    <p><span class="label">Client:</span> {{ $booking->client_name ?? '-' }} | <span class="label">Phone:</span> {{ $booking->client_phone ?? '-' }}</p>
    <p><span class="label">Business:</span> {{ $booking->business_name ?? '-' }} ({{ $booking->business_type ?? '-' }})</p>
  </div>

  <div class="section">
    <div class="section-title">تفاصيل الحجز</div>
    <table>
      <thead>
        <tr>
          <th>التاريخ</th>
          <th>وقت البداية</th>
          <th>وقت الانتهاء</th>
          <th>المدة (ساعات)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ optional($booking->booking_date)->format('Y-m-d') ?? $booking->booking_date }}</td>
          <td>{{ $booking->start_time }}</td>
          <td>{{ $booking->end_time }}</td>
          <td>{{ $booking->duration_hours ?? '-' }}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div class="section">
    <div class="section-title">نوع المشروع</div>
    <p>{{ $booking->project_type }} {{ $booking->custom_project_type ? ' - '.$booking->custom_project_type : '' }}</p>
  </div>

  @if($booking->additional_services)
  <div class="section">
    <div class="section-title">خدمات إضافية</div>
    <p>{{ implode(', ', (array)$booking->additional_services) }}</p>
  </div>
  @endif

  @if($booking->special_notes)
  <div class="section">
    <div class="section-title">ملاحظات خاصة</div>
    <p>{{ $booking->special_notes }}</p>
  </div>
  @endif

  <div class="section">
    <div class="section-title">الموافقات</div>
    <p><span class="label">مقدم الطلب:</span> {{ $booking->requester->name ?? 'N/A' }} (ID: {{ $booking->requester_id }})</p>
    <p><span class="label">المدير المباشر:</span> {{ $booking->directManager->name ?? 'N/A' }}</p>
  </div>

  <div style="margin-top:20px; font-size:11px; color:#666">Generated at {{ now()->toDateTimeString() }}</div>
</body>
</html>
