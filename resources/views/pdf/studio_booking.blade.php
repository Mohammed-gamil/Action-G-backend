<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Studio Booking - {{ $booking->request_id }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #222 }
    .header { text-align: center; margin-bottom: 16px }
    .section { margin-bottom: 12px }
    .label { font-weight: 700 }
    table { width:100%; border-collapse: collapse }
    td, th { padding:6px; vertical-align: top }
    .border { border:1px solid #ddd }
  </style>
</head>
<body>
  <div class="header">
    <h2>Studio Booking</h2>
    <p>{{ $booking->request_id }} - {{ $booking->title }}</p>
  </div>

  <div class="section">
    <p><span class="label">Client:</span> {{ $booking->client_name ?? '-' }} | <span class="label">Phone:</span> {{ $booking->client_phone ?? '-' }}</p>
    <p><span class="label">Business:</span> {{ $booking->business_name ?? '-' }} ({{ $booking->business_type ?? '-' }})</p>
  </div>

  <div class="section border">
    <table>
      <tr>
        <th class="label">Date</th>
        <th class="label">Start</th>
        <th class="label">End</th>
        <th class="label">Duration (hrs)</th>
      </tr>
      <tr>
        <td>{{ optional($booking->booking_date)->format('Y-m-d') ?? $booking->booking_date }}</td>
        <td>{{ $booking->start_time }}</td>
        <td>{{ $booking->end_time }}</td>
        <td>{{ $booking->duration_hours ?? '-' }}</td>
      </tr>
    </table>
  </div>

  <div class="section">
    <p class="label">Project Type:</p>
    <p>{{ $booking->project_type }} {{ $booking->custom_project_type ? ' - '.$booking->custom_project_type : '' }}</p>
  </div>

  @if($booking->additional_services)
  <div class="section">
    <p class="label">Additional Services:</p>
    <p>{{ implode(', ', (array)$booking->additional_services) }}</p>
  </div>
  @endif

  @if($booking->special_notes)
  <div class="section">
    <p class="label">Special Notes:</p>
    <p>{{ $booking->special_notes }}</p>
  </div>
  @endif

  <div class="section">
    <p class="label">Requested By:</p>
    <p>{{ $booking->requester->name ?? 'N/A' }} (ID: {{ $booking->requester_id }})</p>
    <p class="label">Direct Manager:</p>
    <p>{{ $booking->directManager->name ?? 'N/A' }}</p>
  </div>

  <div style="margin-top:20px; font-size:11px; color:#666">Generated at {{ now()->toDateTimeString() }}</div>
</body>
</html>
