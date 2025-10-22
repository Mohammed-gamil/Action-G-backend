<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    use HasFactory;

    protected $table = 'tbl_visits';

    protected $fillable = [
        'client_id',
        'rep_id',
        'visit_date',
        'status',
        'visit_type',
        'visit_result',
        'visit_reason',
        'follow_up_date',
        'location_lat',
        'location_lng',
        'rep_notes',
        'admin_notes',
        'submitted_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'follow_up_date' => 'date',
        'location_lat' => 'decimal:8',
        'location_lng' => 'decimal:8',
        'submitted_at' => 'datetime',
    ];

    protected $appends = ['rep_name'];

    /**
     * Get the client for this visit
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Get the sales rep for this visit
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rep_id');
    }

    /**
     * Get the product category
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Get the admin who approved
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }

    /**
     * Get the files for this visit
     */
    public function files(): HasMany
    {
        return $this->hasMany(VisitFile::class, 'visit_id');
    }

    /**
     * Get the status history for this visit
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(VisitStatusHistory::class, 'visit_id');
    }

    /**
     * Get rep name attribute
     */
    public function getRepNameAttribute(): ?string
    {
        return $this->salesRep ? $this->salesRep->name : null;
    }

    /**
     * Scope for filtering by rep
     */
    public function scopeForRep($query, $repId)
    {
        return $query->where('rep_id', $repId);
    }

    /**
     * Scope for filtering by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for date range
     */
    public function scopeDateRange($query, $from, $to)
    {
        if ($from) {
            $query->where('visit_date', '>=', $from);
        }
        if ($to) {
            $query->where('visit_date', '<=', $to);
        }
        return $query;
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->whereHas('client', function ($cq) use ($search) {
                $cq->where('store_name', 'like', "%{$search}%")
                   ->orWhere('contact_person', 'like', "%{$search}%");
            })
            ->orWhere('rep_notes', 'like', "%{$search}%")
            ->orWhere('admin_notes', 'like', "%{$search}%");
        });
    }
}
