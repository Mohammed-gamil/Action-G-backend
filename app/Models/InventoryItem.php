<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'quantity',
        'reserved_quantity',
        'unit',
        'unit_cost',
        'location',
        'condition',
        'last_maintenance_date',
        'next_maintenance_date',
        'is_active',
        'notes',
        'added_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'last_maintenance_date' => 'date',
        'next_maintenance_date' => 'date',
    ];

    protected $appends = ['available_quantity', 'is_in_stock', 'needs_maintenance'];

    // Relationships
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function requestItems(): HasMany
    {
        return $this->hasMany(RequestInventoryItem::class);
    }

    // Accessors
    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function getIsInStockAttribute(): bool
    {
        return $this->available_quantity > 0;
    }

    public function getNeedsMaintenanceAttribute(): bool
    {
        if (!$this->next_maintenance_date) {
            return false;
        }
        return now()->greaterThanOrEqualTo($this->next_maintenance_date);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->whereRaw('quantity - reserved_quantity > 0');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('code', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    // Methods
    public function canReserve(int $quantity): bool
    {
        return $this->is_active && $this->available_quantity >= $quantity;
    }

    public function reserve(int $quantity, ?int $requestId = null, ?int $userId = null): bool
    {
        if (!$this->canReserve($quantity)) {
            return false;
        }

        $this->reserved_quantity += $quantity;
        $this->save();

        // Log transaction
        if ($userId) {
            InventoryTransaction::create([
                'inventory_item_id' => $this->id,
                'type' => 'RESERVE',
                'quantity' => $quantity,
                'quantity_before' => $this->quantity,
                'quantity_after' => $this->quantity,
                'related_request_id' => $requestId,
                'user_id' => $userId,
                'notes' => "Reserved {$quantity} {$this->unit}(s) for request #{$requestId}",
            ]);
        }

        return true;
    }

    public function release(int $quantity, ?int $requestId = null, ?int $userId = null): bool
    {
        if ($this->reserved_quantity < $quantity) {
            return false;
        }

        $this->reserved_quantity -= $quantity;
        $this->save();

        // Log transaction
        if ($userId) {
            InventoryTransaction::create([
                'inventory_item_id' => $this->id,
                'type' => 'RELEASE',
                'quantity' => $quantity,
                'quantity_before' => $this->quantity,
                'quantity_after' => $this->quantity,
                'related_request_id' => $requestId,
                'user_id' => $userId,
                'notes' => "Released {$quantity} {$this->unit}(s) from request #{$requestId}",
            ]);
        }

        return true;
    }

    public function allocate(int $quantity, ?int $requestId = null, ?int $userId = null): bool
    {
        if ($this->reserved_quantity < $quantity || $this->quantity < $quantity) {
            return false;
        }

        $this->reserved_quantity -= $quantity;
        $this->quantity -= $quantity;
        $this->save();

        // Log transaction
        if ($userId) {
            InventoryTransaction::create([
                'inventory_item_id' => $this->id,
                'type' => 'OUT',
                'quantity' => -$quantity,
                'quantity_before' => $this->quantity + $quantity,
                'quantity_after' => $this->quantity,
                'related_request_id' => $requestId,
                'user_id' => $userId,
                'notes' => "Allocated {$quantity} {$this->unit}(s) to request #{$requestId}",
            ]);
        }

        return true;
    }

    public function addStock(int $quantity, ?int $userId = null, ?string $notes = null): void
    {
        $quantityBefore = $this->quantity;
        $this->quantity += $quantity;
        $this->save();

        // Log transaction
        if ($userId) {
            InventoryTransaction::create([
                'inventory_item_id' => $this->id,
                'type' => 'IN',
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $this->quantity,
                'user_id' => $userId,
                'notes' => $notes ?? "Added {$quantity} {$this->unit}(s) to stock",
            ]);
        }
    }

    // Boot
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (!$item->code) {
                $item->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        $prefix = 'INV';
        $year = now()->year;

        return \Illuminate\Support\Facades\DB::transaction(function () use ($prefix, $year) {
            $maxCode = static::where('code', 'like', "{$prefix}-{$year}-%")
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) as max_seq")
                ->value('max_seq');

            $nextSeq = ((int) $maxCode ?? 0) + 1;

            return sprintf('%s-%d-%04d', $prefix, $year, $nextSeq);
        });
    }
}
