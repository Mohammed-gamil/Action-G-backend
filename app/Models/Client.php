<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $table = 'tbl_clients';

    protected $fillable = [
        'store_name',
        'contact_person',
        'email',
        'mobile',
        'mobile_2',
        'address',
        'business_type_id',
        'created_by_rep_id',
    ];

    /**
     * Get the business type
     */
    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class, 'business_type_id');
    }

    /**
     * Get the rep who created this client
     */
    public function createdByRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_rep_id');
    }

    /**
     * Get visits for this client
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'client_id');
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
            $q->where('store_name', 'like', "%{$search}%")
              ->orWhere('contact_person', 'like', "%{$search}%")
              ->orWhere('mobile', 'like', "%{$search}%");
        });
    }
}
