<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestQuote extends Model
{
    protected $fillable = [
        'request_id',
        'vendor_name',
        'quote_total',
        'file_path',
        'notes',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'quote_total' => 'decimal:2',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
