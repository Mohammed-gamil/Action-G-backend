<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryRequestItem extends Model
{
    use HasFactory;

    protected $table = 'tbl_inventory_request_items';

    protected $fillable = [
        'inventory_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_approved',
        'expected_return_date',
        'serial_number',
        'condition_before_exit',
        'quantity_returned',
        'condition_after_return',
        'return_notes',
    ];

    protected $casts = [
        'expected_return_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function inventoryRequest()
    {
        return $this->belongsTo(InventoryRequest::class, 'inventory_request_id');
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
