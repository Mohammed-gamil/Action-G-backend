<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RequestInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_allocated',
        'status',
        'expected_return_date',
        'actual_return_date',
        'return_notes',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_allocated' => 'integer',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
    ];

    // Relationships
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    // Methods
    public function canAllocate(): bool
    {
        return $this->status === 'RESERVED' && 
               $this->quantity_allocated === 0 &&
               $this->inventoryItem->canReserve($this->quantity_requested);
    }

    public function allocate(?int $userId = null): bool
    {
        if (!$this->canAllocate()) {
            return false;
        }

        if ($this->inventoryItem->allocate($this->quantity_requested, $this->request_id, $userId)) {
            $this->quantity_allocated = $this->quantity_requested;
            $this->status = 'ALLOCATED';
            $this->save();
            return true;
        }

        return false;
    }
}
