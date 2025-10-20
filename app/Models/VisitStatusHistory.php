<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'tbl_visit_status_history';

    public $timestamps = false;

    protected $fillable = [
        'visit_id',
        'from_status',
        'to_status',
        'changed_by_user_id',
        'notes',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    /**
     * Get the visit
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    /**
     * Get the user who made the change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
