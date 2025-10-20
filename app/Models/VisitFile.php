<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitFile extends Model
{
    use HasFactory;

    protected $table = 'tbl_visit_files';

    public $timestamps = false;

    protected $fillable = [
        'visit_id',
        'file_type',
        'original_filename',
        'stored_filename',
        'file_size_bytes',
        'mime_type',
        'storage_url',
        'upload_status',
        'uploaded_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the visit for this file
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }
}
