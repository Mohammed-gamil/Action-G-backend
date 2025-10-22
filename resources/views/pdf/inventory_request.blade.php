<!doctype html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inventory Request - {{ $request->request_id }}</title>
  <style>
    body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #222 }
    .header { text-align: center; margin-bottom: 8px }
    .section { margin-bottom: 10px }
    table { width:100%; border-collapse: collapse }
    td, th { padding:6px; border:1px solid #ddd }
    .label { font-weight:700 }
  </style>
</head>
<body>
  <div class="header">
    <h2>Inventory Request</h2>
    <p>{{ $request->request_id }} - {{ $request->title }}</p>
  </div>

  <div class="section">
    <p><span class="label">Requester:</span> {{ $request->requester->name ?? '-' }}</p>
    <p><span class="label">Employee:</span> {{ $request->employee_name ?? '-' }} ({{ $request->employee_position ?? '-' }})</p>
  </div>

  <div class="section">
    <table>
      <tr>
        <th>#</th>
        <th>Item</th>
        <th>Qty Requested</th>
        <th>Qty Returned</th>
        <th>Serial</th>
        <th>Condition (Before)</th>
        <th>Condition (After)</th>
      </tr>
      @foreach($request->items as $idx => $item)
      <tr>
        <td>{{ $idx+1 }}</td>
        <td>{{ $item->inventoryItem->name ?? $item->inventory_item_id }}</td>
        <td>{{ $item->quantity_requested }}</td>
        <td>{{ $item->quantity_returned ?? '-' }}</td>
        <td>{{ $item->serial_number ?? '-' }}</td>
        <td>{{ $item->condition_before_exit ?? '-' }}</td>
        <td>{{ $item->condition_after_return ?? '-' }}</td>
      </tr>
      @endforeach
    </table>
  </div>

  <div class="section">
    <p><span class="label">Exit Purpose:</span> {{ $request->exit_purpose ?? '-' }} {{ $request->custom_exit_purpose ? '(' . $request->custom_exit_purpose . ')' : '' }}</p>
    <p><span class="label">Shoot Location:</span> {{ $request->shoot_location ?? '-' }}</p>
    <p><span class="label">Return Supervisor:</span> {{ $request->return_supervisor_name ?? '-' }} | {{ $request->return_supervisor_phone ?? '-' }}</p>
  </div>

  <div style="margin-top:12px; font-size:11px; color:#666">Generated at {{ now()->toDateTimeString() }}</div>
</body>
</html>
